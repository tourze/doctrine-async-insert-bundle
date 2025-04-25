<?php

namespace Tourze\DoctrineAsyncBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Tourze\DoctrineAsyncBundle\DependencyInjection\DoctrineAsyncExtension;
use Tourze\DoctrineAsyncBundle\EventSubscriber\DoctrineCleanSubscriber;
use Tourze\DoctrineAsyncBundle\MessageHandler\InsertTableHandler;
use Tourze\DoctrineAsyncBundle\Service\DoctrineService;

/**
 * DoctrineAsyncExtension 测试类
 */
class DoctrineAsyncExtensionTest extends TestCase
{
    private DoctrineAsyncExtension $extension;
    private ContainerBuilder $container;

    protected function setUp(): void
    {
        $this->extension = new DoctrineAsyncExtension();
        $this->container = new ContainerBuilder();
    }

    public function testLoadRegistersServices(): void
    {
        $this->extension->load([], $this->container);

        // 验证服务定义是否正确加载
        $this->assertTrue($this->container->has(DoctrineCleanSubscriber::class));
        $this->assertTrue($this->container->has(InsertTableHandler::class));
        $this->assertTrue($this->container->has(DoctrineService::class));
    }

    public function testServiceAutoconfigurationAndAutowiring(): void
    {
        $this->extension->load([], $this->container);

        // 验证服务配置是否正确
        $definitions = $this->container->getDefinitions();

        // 检查是否存在自动配置和自动装配设置的服务
        $hasAutowiredService = false;
        $hasAutoconfiguredService = false;

        foreach ($definitions as $definition) {
            if ($definition->isAutowired()) {
                $hasAutowiredService = true;
            }
            if ($definition->isAutoconfigured()) {
                $hasAutoconfiguredService = true;
            }

            if ($hasAutowiredService && $hasAutoconfiguredService) {
                break;
            }
        }

        $this->assertTrue($hasAutowiredService, '至少应该有一个启用了自动装配的服务');
        $this->assertTrue($hasAutoconfiguredService, '至少应该有一个启用了自动配置的服务');
    }
}
