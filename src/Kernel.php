<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Resource\FileResource;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    const CONFIG_EXTS = '.{php,xml,yaml,yml}';

    public function getCacheDir()
    {
        return $this->getProjectDir().'/var/cache/'.$this->environment;
    }

    public function getLogDir()
    {
        return $this->getProjectDir().'/var/log';
    }

    public function registerBundles()
    {
        $contents = require $this->getProjectDir().'/config/bundles.php';
        foreach ($contents as $class => $envs) {
            if (isset($envs['all']) || isset($envs[$this->environment])) {
                yield new $class();
            }
        }
    }

    protected function configureContainer(ContainerBuilder $container, LoaderInterface $loader)
    {
        $container->addResource(new FileResource($this->getProjectDir().'/config/bundles.php'));
        // Feel free to remove the "container.autowiring.strict_mode" parameter
        // if you are using symfony/dependency-injection 4.0+ as it's the default behavior
        $container->setParameter('container.autowiring.strict_mode', true);
        $container->setParameter('container.dumper.inline_class_loader', true);
        $confDir = $this->getProjectDir().'/config';

        $loader->load($confDir.'/{packages}/*'.self::CONFIG_EXTS, 'glob');
        $loader->load($confDir.'/{packages}/'.$this->environment.'/**/*'.self::CONFIG_EXTS, 'glob');
        $loader->load($confDir.'/{services}'.self::CONFIG_EXTS, 'glob');
        $loader->load($confDir.'/{services}_'.$this->environment.self::CONFIG_EXTS, 'glob');

        $relationships = getenv("SENSIOCLOUD_RELATIONSHIPS");

        if (!$relationships) {
            return;
        }

        /* injecting SYMFONY__ params */
        foreach ($_SERVER as $key => $value) {
            if (0 === strpos($key, 'SYMFONY__')) {
                $container->setParameter(strtolower(str_replace('__', '.', substr($key, 9))), $value);
            }
        }

        $relationships = json_decode(base64_decode($relationships), true);

        foreach ($relationships['database'] as $endpoint) {
            if (empty($endpoint['query']['is_master'])) {
                continue;
            }

            $container->setParameter('database_driver', 'pdo_'.$endpoint['scheme']);
            $container->setParameter('database_host', $endpoint['host']);
            $container->setParameter('database_port', $endpoint['port']);
            $container->setParameter('database_name', $endpoint['path']);
            $container->setParameter('database_user', $endpoint['username']);
            $container->setParameter('database_password', $endpoint['password']);
            $container->setParameter('database_path', '');
        }

        if (empty($relationships['redis'])) {
            return;
        }

        $redis = array_shift($relationships['redis']);
        $container->setParameter('redis_host', $redis['host']);
        $container->setParameter('redis_port', $redis['port']);

    }

    protected function configureRoutes(RouteCollectionBuilder $routes)
    {
        $confDir = $this->getProjectDir().'/config';

        $routes->import($confDir.'/{routes}/*'.self::CONFIG_EXTS, '/', 'glob');
        $routes->import($confDir.'/{routes}/'.$this->environment.'/**/*'.self::CONFIG_EXTS, '/', 'glob');
        $routes->import($confDir.'/{routes}'.self::CONFIG_EXTS, '/', 'glob');
    }
}
