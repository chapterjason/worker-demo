<?php

namespace App\Controller;

use App\Message\SleepMessage;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use SoureCode\Bundle\Worker\Entity\MessengerMessage;
use SoureCode\Bundle\Worker\Manager\WorkerManager;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Exception\MessageDecodingFailedException;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Messenger\Transport\Serialization\SerializerInterface;

class MessengerMessageCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly MessageBusInterface    $messageBus,
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkerManager          $workerManager,
        private readonly SerializerInterface    $serializer,
    )
    {
    }

    public static function getEntityFqcn(): string
    {
        return MessengerMessage::class;
    }

    public function configureActions(Actions $actions): Actions
    {
        $failedQueueNames = $this->workerManager->getFailureTransportNames();

        $retryAction = Action::new('retry', 'Retry')
            ->linkToCrudAction('retry')
            ->setCssClass('text-warning')
            ->displayIf(fn(MessengerMessage $messengerMessage) => in_array($messengerMessage->getQueueName(), $failedQueueNames, true))
            ->setHtmlAttributes(['title' => 'Retry message']);

        $dispatchSleepMessage = Action::new('dispatchSleepMessage', 'Dispatch sleep message')
            ->linkToCrudAction('dispatchSleepMessage')
            ->createAsGlobalAction()
            ->setHtmlAttributes(['title' => 'Dispatch sleep message']);

        return parent::configureActions($actions)
            ->add(Crud::PAGE_INDEX, $dispatchSleepMessage)
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $retryAction);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return parent::configureFilters($filters)
            ->add(ChoiceFilter::new('queueName')
                ->canSelectMultiple()
                ->setChoices($this->getQueueNames()));
    }

    private function getQueueNames(): array
    {
        $queueNames = [
            'default' => 'default',
        ];

        foreach ($this->workerManager->getReceiverNames() as $receiverName) {
            $queueNames[$receiverName] = $receiverName;
        }

        return $queueNames;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud)
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->hideOnForm(),
            TextField::new('body')
                ->setSortable(false)
                ->formatValue(function ($value, MessengerMessage $messengerMessage) {
                    try {
                        $envelope = $messengerMessage->getDecodedEnvelope($this->serializer);
                        $message = $envelope->getMessage();

                        return $message::class;
                    } catch (MessageDecodingFailedException $e) {
                        return "Decoding failed: {$e->getMessage()}";
                    }
                })
                ->onlyOnIndex(),
            TextField::new('headers')->hideOnForm(),
            ChoiceField::new('queueName')
                ->setChoices($this->getQueueNames())
            ,
            DateTimeField::new('createdAt'),
            DateTimeField::new('availableAt'),
            DateTimeField::new('deliveredAt'),
            TextField::new('body', 'Result')
                ->formatValue(function ($value, MessengerMessage $messengerMessage) {
                    try {
                        $envelope = $messengerMessage->getDecodedEnvelope($this->serializer);
                        $handledStamp = $envelope->last(HandledStamp::class);

                        return json_encode($handledStamp, JSON_THROW_ON_ERROR);
                    } catch (MessageDecodingFailedException $e) {
                        return "Decoding failed: {$e->getMessage()}";
                    }
                })->hideOnIndex()
                ->onlyOnIndex(),
            TextField::new('body')
                ->formatValue(function ($value, MessengerMessage $messengerMessage) {
                    try {
                        return $messengerMessage->getDecodedEnvelope($this->serializer);
                    } catch (MessageDecodingFailedException $e) {
                        return "Decoding failed: {$e->getMessage()}";
                    }
                })
                ->hideOnForm()
                ->hideOnIndex(),
        ];
    }

    public function retry(AdminContext $adminContext): Response
    {
        $entity = $adminContext->getEntity();
        /**
         * @var MessengerMessage $instance
         */
        $instance = $entity->getInstance();

        $envelope = $instance->getDecodedEnvelope($this->serializer);

        $this->messageBus->dispatch($envelope->getMessage());

        $this->deleteEntity($this->entityManager, $instance);

        return $this->redirect($adminContext->getReferrer());
    }

    public function dispatchSleepMessage(AdminContext $adminContext): Response
    {
        $this->messageBus->dispatch(new SleepMessage(5));

        return $this->redirect($adminContext->getReferrer());
    }
}
