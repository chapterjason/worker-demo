<?php

namespace App\Controller;

use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use RuntimeException;
use SoureCode\Bundle\Worker\Entity\Worker;
use SoureCode\Bundle\Worker\Entity\WorkerStatus;
use SoureCode\Bundle\Worker\Manager\WorkerManager;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\KernelInterface;

class WorkerCrudController extends AbstractCrudController
{

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly WorkerManager          $workerManager,
    )
    {
    }

    public static function getEntityFqcn(): string
    {
        return Worker::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return parent::configureCrud($crud);
    }


    public function configureActions(Actions $actions): Actions
    {
        $startAction = Action::new('start', 'Start')
            ->linkToCrudAction('start')
            ->displayIf(fn(Worker $worker) => !$worker->isRunning());

        $resetAction = Action::new('reset', 'Reset')
            ->linkToCrudAction('reset')
            ->displayIf(fn(Worker $worker) => !$worker->isRunning());

        $stopAction = Action::new('stop', 'Stop')
            ->linkToCrudAction('stop')
            ->setHtmlAttributes([
                'data-controller' => 'are-you-sure',
            ])
            ->addCssClass('text-danger')
            ->displayIf(fn(Worker $worker) => $worker->isRunning());

        $stopAllAction = Action::new('stopAll', 'Stop All')
            ->linkToCrudAction('stopAll')
            ->setHtmlAttributes([
                'data-controller' => 'are-you-sure',
            ])
            ->addCssClass('btn btn-danger')
            ->createAsGlobalAction();

        $startAllAction = Action::new('startAll', 'Start All')
            ->linkToCrudAction('startAll')
            ->createAsGlobalAction();

        $restartAction = Action::new('restart', 'Restart')
            ->linkToCrudAction('restart')
            ->setHtmlAttributes([
                'data-controller' => 'are-you-sure',
            ])
            ->addCssClass('text-danger')
            ->displayIf(fn(Worker $worker) => $worker->isRunning());

        $gracefullyRestartAction = Action::new('gracefullyRestart', 'Gracefully Restart')
            ->linkToCrudAction('gracefullyRestart')
            ->createAsGlobalAction()
            ->setHtmlAttributes([
                'data-controller' => 'are-you-sure',
            ])
            ->addCssClass('btn-danger');

        return parent::configureActions($actions)
            ->add(Crud::PAGE_DETAIL, $startAction)
            ->add(Crud::PAGE_INDEX, $resetAction)
            ->add(Crud::PAGE_INDEX, $gracefullyRestartAction)
            ->add(Crud::PAGE_INDEX, $startAction)
            ->add(Crud::PAGE_INDEX, $restartAction)
            ->add(Crud::PAGE_INDEX, $stopAction)
            ->add(Crud::PAGE_INDEX, $stopAllAction)
            ->add(Crud::PAGE_INDEX, $startAllAction)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->remove(Crud::PAGE_INDEX, Action::BATCH_DELETE);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->hideOnForm(),
            ChoiceField::new('transports')
                ->allowMultipleChoices()
                ->setChoices([
                    'async' => 'async',
                ])
                ->setHelp('The transport to use for this worker'),
            ArrayField::new('queues')
                ->setRequired(false)
                ->setHelp('The queues to listen to'),
            IntegerField::new('messageLimit')
                ->hideOnIndex()
                ->setHelp('The maximum number of messages to process before the worker exits'),
            IntegerField::new('failureLimit')
                ->hideOnIndex()
                ->setHelp('The maximum number of failures to process before the worker exits'),
            IntegerField::new('memoryLimit')
                ->hideOnIndex()
                ->setHelp('The maximum amount of memory to use before the worker exits'),
            IntegerField::new('timeLimit')
                ->hideOnIndex()
                ->setHelp('The maximum amount of time to run before the worker exits'),
            IntegerField::new('sleep')
                ->hideOnIndex()
                ->setHelp('The number of seconds to sleep when no jobs are found'),
            BooleanField::new('reset')
                ->hideOnIndex()
                ->renderAsSwitch(false)
                ->setHelp('Reset the worker state before processing the next message'),
            ChoiceField::new('status')
                ->setFormType(EnumType::class)
                ->setFormTypeOption('class', WorkerStatus::class)
                ->setTemplatePath('_worker_status.html.twig')
                ->hideOnForm()
            ,
            IntegerField::new('handled')
                ->hideOnForm(),
            IntegerField::new('failed')
                ->hideOnForm(),
            DateTimeField::new('startedAt')
                ->hideOnForm(),
            DateTimeField::new('lastHeartbeat')
                ->hideOnForm(),
        ];
    }

    public function reset(AdminContext $context): RedirectResponse
    {
        $entity = $context->getEntity();

        /**
         * @var Worker $instance
         */
        $instance = $entity->getInstance();

        $instance->setHandled(0);
        $instance->setFailed(0);

        $this->updateEntity($this->entityManager, $instance);

        return $this->redirect($context->getReferrer());
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->addFlash('success', 'Worker updated successfully. Restart it to apply changes.');

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function startAll(AdminContext $context): RedirectResponse
    {
        $started = $this->workerManager->startAll();

        if ($started) {
            $this->addFlash('success', 'Workers started.');
        } else {
            $this->addFlash('error', 'Workers failed to start.');
        }

        return $this->redirect($context->getReferrer());
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Worker && $entityInstance->isRunning()) {
            throw new RuntimeException('Worker is running and cannot be deleted.');
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        $this->addFlash('success', 'Worker created successfully. Start it to process messages.');

        parent::persistEntity($entityManager, $entityInstance);
    }

    public function restart(AdminContext $context): RedirectResponse
    {
        $entity = $context->getEntity();

        /**
         * @var Worker $instance
         */
        $instance = $entity->getInstance();

        $stopped = $this->workerManager->start($instance);

        if ($stopped) {
            $this->addFlash('success', 'Worker stopped.');

            $this->entityManager->refresh($instance);

            $started = $this->workerManager->start($instance);

            if ($started) {
                $this->addFlash('success', 'Worker started.');
            } else {
                $this->addFlash('error', 'Worker failed to start.');
            }
        } else {
            $this->addFlash('error', 'Failed to stop worker.');
        }

        sleep(3);

        return $this->redirect($context->getReferrer());
    }

    public function stop(AdminContext $context): RedirectResponse
    {
        $entity = $context->getEntity();

        /**
         * @var Worker $instance
         */
        $instance = $entity->getInstance();

        $stopped = $this->workerManager->stop($instance);

        if ($stopped) {
            $this->addFlash('success', 'Worker stopped.');
        } else {
            $this->addFlash('error', 'Failed to stop worker.');
        }

        return $this->redirect($context->getReferrer());
    }

    public function start(AdminContext $context): RedirectResponse
    {
        $entity = $context->getEntity();

        /**
         * @var Worker $instance
         */
        $instance = $entity->getInstance();

        $started = $this->workerManager->start($instance);

        if ($started) {
            $this->addFlash('success', 'Worker started.');
        } else {
            $this->addFlash('error', 'Worker failed to start.');
        }

        return $this->redirect($context->getReferrer());
    }

    public function gracefullyRestart(AdminContext $context, KernelInterface $kernel): RedirectResponse
    {
        $application = new Application($kernel);
        $application->setAutoExit(false);

        $application->run(
            new ArrayInput([
                'command' => 'messenger:stop-workers',
            ]),
            new NullOutput()
        );

        return $this->redirect($context->getReferrer());
    }
}
