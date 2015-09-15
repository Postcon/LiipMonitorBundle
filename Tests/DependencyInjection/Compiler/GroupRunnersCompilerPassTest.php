<?php

namespace Liip\MonitorBundle\Tests\DependencyInjection\Compiler;

use Liip\MonitorBundle\DependencyInjection\Compiler\GroupRunnersCompilerPass;
use Matthias\SymfonyDependencyInjectionTest\PhpUnit\AbstractCompilerPassTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

/**
 * @author Kevin Bond <kevinbond@gmail.com>
 */
class GroupRunnersCompilerPassTest extends AbstractCompilerPassTestCase
{
    public function testProcess()
    {
        $defaultGroup = 'groupe par défaut';

        $runner = new Definition();
        $this->setDefinition('liip_monitor.runner', $runner);
        $this->setParameter('liip_monitor.default_group', $defaultGroup);

        $fooCheck = new Definition();
        $fooCheck->addTag('liip_monitor.check', array('group' => 'foo'));
        $fooCheck->addTag('liip_monitor.check', array('group' => 'foobar'));
        $this->setDefinition('acme.check.foo', $fooCheck);

        $barCheckCollection = new Definition();
        $barCheckCollection->addTag('liip_monitor.check_collection', array('group' => 'bar'));
        $this->setDefinition('acme.check.bar', $barCheckCollection);

        $this->compile();

        $this->assertContainerBuilderHasAlias('liip_monitor.runner', 'liip_monitor.runner_' . $defaultGroup);
        $this->assertContainerBuilderHasService('liip_monitor.runner_' . $defaultGroup);
        $this->assertContainerBuilderHasService('liip_monitor.runner_foo');
        $this->assertContainerBuilderHasService('liip_monitor.runner_foobar');
        $this->assertContainerBuilderHasService('liip_monitor.runner_bar');
    }

    protected function registerCompilerPass(ContainerBuilder $container)
    {
        $container->addCompilerPass(new GroupRunnersCompilerPass());
    }
}
