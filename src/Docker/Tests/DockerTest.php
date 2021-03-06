<?php

namespace Docker\Tests;

use Docker\Docker;
use Docker\Context\Context;
use Docker\Container;
use Docker\Context\ContextBuilder;

class DockerTest extends TestCase
{
    public function testBuild()
    {
        $contextBuilder = new ContextBuilder();
        $contextBuilder->from('ubuntu:precise');
        $contextBuilder->add('/test', 'test file content');

        $docker  = $this->getDocker();
        $content = "";

        $response = $docker->build($contextBuilder->getContext(), 'foo', function ($output) use (&$content) {
            if (isset($output['stream'])) {
                $content .= $output['stream'];
            }
        });

        $this->assertRegExp('/Successfully built/', $content);
    }

    public function testBuildWithExistingDirectory()
    {
        $docker     = $this->getDocker();
        $directory  = __DIR__.DIRECTORY_SEPARATOR."Context".DIRECTORY_SEPARATOR."context-test";
        $context    = new Context($directory);
        $timecalled = 0;

        $docker->build($context, 'foo', function ($output) use (&$content, &$timecalled) {
            if (isset($output['stream'])) {
                $content .= $output['stream'];
            }
            $timecalled++;
        });

        $this->assertRegExp('/Successfully built/', $content);
        $this->assertGreaterThan(1, $timecalled);
    }

    public function testCommit()
    {
        $container = new Container();
        $container->setImage('ubuntu:precise');
        $container->setCmd(['/bin/true']);

        $docker = $this->getDocker();
        $manager = $docker->getContainerManager();

        $manager->run($container);
        $manager->wait($container);

        $image = $docker->commit($container, ['repo' => 'test', 'tag' => 'foo']);

        $this->assertNotEmpty($image->getId());
        $this->assertEquals('test', $image->getRepository());
        $this->assertEquals('foo', $image->getTag());
    }

    public function testGetContainerManager()
    {
        $docker = $this->getDocker();

        $this->assertInstanceOf('Docker\\Manager\\ContainerManager', $docker->getContainerManager());
    }
}
