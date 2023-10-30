<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    /**
     * This is a copy of the original method from the parent class.
     * The only difference is that we use the $_SERVER['DOCUMENT_ROOT'] variable instead of the ClassReflection on the Kernel class.
     * This allows us to return the symlink path instead of the real path.
     *
     * @return string
     */
    public function getProjectDir(): string
    {
        if (!isset($this->projectDir)) {
            $dir = $_SERVER['DOCUMENT_ROOT'];

            $dir = $rootDir = \dirname($dir);
            while (!is_file($dir.'/composer.json')) {
                if ($dir === \dirname($dir)) {
                    return $this->projectDir = $rootDir;
                }
                $dir = \dirname($dir);
            }
            $this->projectDir = $dir;
        }

        return $this->projectDir;
    }
}
