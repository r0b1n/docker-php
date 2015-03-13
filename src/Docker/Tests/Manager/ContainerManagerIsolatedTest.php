<?php

namespace Docker\Tests\Manager;

use Docker\Container;
use Docker\Context\ContextBuilder;
use Docker\Port;
use Docker\Tests\IsolatedTestCase;
use GuzzleHttp\Exception\RequestException;



use GuzzleHttp\Message\Response;

class ContainerManagerIsolatedTest extends IsolatedTestCase
{
    /**
     * Return a container manager
     *
     * @return \Docker\Manager\ContainerManager
     */
    private function getManager()
    {
        return $this->getDocker()->getContainerManager();
    }

    private function getMockedManager($list, $clientOptions = [])
    {
        $manager = new \Docker\Manager\ContainerManager($this->getMockedClient($list, $clientOptions));
        return $manager;
    }


    public function createProvider()
    {
        return [
            [ $this->fromFile('/data/container/07-create.json'), 'e90e34656806']
        ];
    }

    public function killProvider()
    {
        return [
            ['e90e34656806', 'SIGHUP']
        ];
    }

    public function inspectProvider()
    {
        return [
            [
                'e90e34656806', 
                $this->fromFile('/data/container/08-inspect.json'), 
                '/boring_euclid'
            ]
        ];
    }

    public function startProvider()
    {
        return [
            [
                'e90e34656806', 
                $this->fromFile('/data/container/08-inspect.json')
            ]
        ];
    }

    public function stopProvider()
    {
        return [
            [
                'e90e34656806', 
                30,
                $this->fromFile('/data/container/08-inspect.json')
            ]
        ];
    }

    public function removeProvider()
    {
        return [
            [
                'e90e34656806',
                false
            ],
            [
                'abcdef012345',
                true
            ]
        ];
    }

    public function restartProvider()
    {
        return [
            [
                'e90e34656806',
                10
            ],
            [
                'abcdef012345',
                7
            ]
        ];
    }

    public function exportProvider()
    {
        return [
            [
                'e90e34656806',
                $this->fromFile('/data/container/09-export.json'),
                'TAR_STREAM_HERE'
            ],
        ];
    }

    public function changesProvider()
    {
        return [
            [
                'e90e34656806',
                $this->fromFile('/data/container/10-changes.json'),
                3
            ],
        ];
    }

    public function topProvider()
    {
        return [
            [
                'e90e34656806',
                'aux',
                $this->fromFile('/data/container/11-top.json'),
            ],
            [
                'abcdef012345',
                'auxj',
                $this->fromFile('/data/container/11-top.json'),
            ],
        ];
    }

    public function execProvider()
    {
        return [
            [
                'e90e34656806',
                ['sleep', '5'],
                $this->fromFile('/data/container/12-exec.json'),
                'f90e34656806'
            ]
        ];
    }

    public function _testFindAll()
    {
        $container = new Container(['Image' => 'ubuntu:precise', 'Cmd' => ['/bin/sleep', '1']]);

        $manager = $this->getManager();
        $manager->run($container);

        $this->assertInternalType('array', $manager->findAll());
    }

    /**
     * @dataProvider createProvider
     */
    public function testCreate($json, $id)
    {
        $data = ['Image' => 'ubuntu:precise', 'Cmd' => ['/bin/true']];
        $container = new Container($data);

        $manager = $this->getMockedManager([
            $json
        ]);
        $manager->create($container);
        $history = $this->getRequestsHistory();
        $body = $history->getLastRequest()->getBody()->__toString();
        $this->assertEquals("POST", $history->getLastRequest()->getMethod());
        $this->assertEquals("/containers/create", $history->getLastRequest()->getPath());
        $this->assertSame(json_decode($body, true), $data);

        $this->assertNotEmpty($container->getId());
        $this->assertEquals($id, $container->getId());
    }

    /**
     * @dataProvider inspectProvider
     */
    public function testInspect($containerId, $json, $name)
    {
        $manager = $this->getMockedManager([
            $json
        ]);

        //$this->assertEquals(null, $manager->find('foo'));

        $container = new Container(['Image' => 'ubuntu:precise', 'Cmd' => ['/bin/true']]);
        $container->setId($containerId);
        $manager->inspect($container);

        $this->assertInternalType('array', $container->getRuntimeInformations());
        $this->assertEquals($name, $container->getRuntimeInformations()['Name']);

        $history = $this->getRequestsHistory();

        $this->assertEquals("GET", $history->getLastRequest()->getMethod());
        $this->assertEquals("/containers/{$containerId}/json", $history->getLastRequest()->getPath());
    }



    /**
     * @dataProvider killProvider
     */
    public function testKill($containerId, $signal)
    {
        $manager = $this->getMockedManager([
            new Response(204)
        ]);


        $container = new Container([]);
        $container->setId($containerId);
        
        $manager->kill($container, $signal);
        
        $history = $this->getRequestsHistory();
        $requests = $history->getRequests();

        $this->assertContains('POST', $requests[0]->getMethod());
        $this->assertContains("/containers/{$containerId}/kill", $requests[0]->getPath());
        $this->assertContains($requests[0]->getQuery()['signal'], $signal);
    }

    /**
     * @dataProvider startProvider
     */
    public function testStart($containerId, $inspectJson)
    {
        $factory = $this
            ->getMockBuilder('\GuzzleHttp\Message\MessageFactory')
            ->setMethods(['add_callback', 'add_wait'])
            ->getMock();
        $manager = $this->getMockedManager([
            new Response(204),
            $inspectJson
        ], [
            'message_factory' => $factory
        ]);



        $container = new Container(['Image' => 'ubuntu:precise', 'Cmd' => ['/bin/true']]);
        $container->setId($containerId);

        $manager->start($container);


        $history = $this->getRequestsHistory();
        $requests = $history->getRequests();

        $this->assertEquals('POST', $requests[0]->getMethod());
        $this->assertEquals("/containers/{$containerId}/start", $requests[0]->getPath());
    }

    /**
     * @dataProvider stopProvider
     */
    public function testStop($containerId, $timeout, $inspectJson)
    {
        $factory = $this
            ->getMockBuilder('\GuzzleHttp\Message\MessageFactory')
            ->setMethods(['add_callback', 'add_wait'])
            ->getMock();
        $manager = $this->getMockedManager([
            new Response(204),
            $inspectJson
        ], [
            'message_factory' => $factory
        ]);



        $container = new Container(['Image' => 'ubuntu:precise', 'Cmd' => ['/bin/true']]);
        $container->setId($containerId);

        $manager->stop($container, $timeout);


        $history = $this->getRequestsHistory();
        $requests = $history->getRequests();

        $this->assertEquals('POST', $requests[0]->getMethod());
        $this->assertEquals("/containers/{$containerId}/stop", $requests[0]->getPath());
        $this->assertEquals($requests[0]->getQuery()['t'], $timeout);
    }

    /**
     * @dataProvider restartProvider
     */
    public function testRestart($containerId, $timeout)
    {
        $manager = $this->getMockedManager([
            new Response(204)
        ]);
        
        $container = new Container(['Image' => 'docker-php-restart-test', 'Cmd' => ['/daemon.sh']]);
        $container->setId($containerId);
        $manager->restart($container, $timeout);


        $history = $this->getRequestsHistory();
        $requests = $history->getRequests();

        $this->assertEquals('POST', $requests[0]->getMethod());
        $this->assertEquals("/containers/{$containerId}/restart", $requests[0]->getPath());
        $this->assertEquals($requests[0]->getQuery()['t'], $timeout);
    }

    /**
     * @dataProvider removeProvider
     */
    public function testRemove($containerId, $withVolumes)
    {
        $container = new Container(['Image' => 'ubuntu:precise', 'Cmd' => ['date']]);
        $container->setId($containerId);

        $factory = $this
            ->getMockBuilder('\GuzzleHttp\Message\MessageFactory')
            ->setMethods(['add_callback', 'add_wait'])
            ->getMock();
        $manager = $this->getMockedManager([
            new Response(204)
        ], [
            'message_factory' => $factory
        ]);
        
        $manager->remove($container, $withVolumes);

        $history = $this->getRequestsHistory();
        $requests = $history->getRequests();

        $this->assertEquals('DELETE', $requests[0]->getMethod());
        $this->assertEquals("/containers/{$containerId}", $requests[0]->getPath());
        $this->assertContains($requests[0]->getQuery()['v'], $withVolumes ? $this->_booleanTrue : $this->_booleanFalse);
    }


    /**
     * @dataProvider exportProvider
     */
    public function testExport($containerId, $json, $tar_stream)
    {
        $container = new Container(['Image' => 'ubuntu:precise', 'Cmd' => ['date']]);
        $container->setId($containerId);

        $manager = $this->getMockedManager([
            $json
        ]);
        
        $stream = $manager->export($container);

        $history = $this->getRequestsHistory();
        $requests = $history->getRequests(); 

        $this->assertEquals($tar_stream, $stream->__toString());
        $this->assertEquals('GET', $requests[0]->getMethod());
        $this->assertEquals("/containers/{$containerId}/export", $requests[0]->getPath());
    }

    /**
     * @dataProvider changesProvider
     */
    public function testChanges($containerId, $json, $count)
    {
        $container = new Container(['Image' => 'ubuntu:precise', 'Cmd' => ['date']]);
        $container->setId($containerId);

        $manager = $this->getMockedManager([
            $json
        ]);
        
        $changes = $manager->changes($container);

        $history = $this->getRequestsHistory();
        $requests = $history->getRequests(); 

        $this->assertEquals($count, count($changes));
        $this->assertEquals('GET', $requests[0]->getMethod());
        $this->assertEquals("/containers/{$containerId}/changes", $requests[0]->getPath());
    }

    /**
     * @dataProvider topProvider
     */
    public function testTop($containerId, $ps_args, $json)
    {
        $container = new Container(['Image' => 'ubuntu:precise', 'Cmd' => ['sleep', '2']]);
        $container->setId($containerId);
        $manager = $this->getMockedManager([
            $json
        ]);
        
        $processes = $manager->top($container, $ps_args);

        $this->assertCount(2, $processes);
        $this->assertArrayHasKey('COMMAND', $processes[0]);


        $history = $this->getRequestsHistory();
        $requests = $history->getRequests(); 

        $this->assertEquals('GET', $requests[0]->getMethod());
        $this->assertEquals("/containers/{$containerId}/top", $requests[0]->getPath());
        $this->assertEquals($requests[0]->getQuery()['ps_args'], $ps_args);
    }

    /**
     * @dataProvider execProvider
     */
    public function testExec($containerId, $cmd, $json, $expExecId)
    {
        $container = new Container(['Image' => 'ubuntu:precise', 'Cmd' => ['sleep', '2']]);
        $container->setId($containerId);
        $manager = $this->getMockedManager([
            $json
        ]);
        
        $execId = $manager->exec($container, ['sleep', '5']);
        // TODO: test execstart

        $this->assertEquals($expExecId, $execId);
        //$this->assertArrayHasKey('COMMAND', $processes[0]);


        $history = $this->getRequestsHistory();
        $requests = $history->getRequests(); 

        $this->assertEquals('POST', $requests[0]->getMethod());
        $this->assertEquals("/containers/{$containerId}/exec", $requests[0]->getPath());
        //$this->assertEquals($requests[0]->getQuery()['ps_args'], $ps_args);
        // TODO: add command check


    }


    public function _testInteract()
    {
        $container = new Container([
            'Image' => 'ubuntu:precise',
            'Cmd'   => ['/bin/bash'],
            'AttachStdin'  => false,
            'AttachStdout' => true,
            'AttachStderr' => true,
            'OpenStdin'    => true,
            'Tty'          => true,
        ]);

        $manager = $this->getManager();
        $manager->create($container);
        $stream = $manager->interact($container);
        $manager->start($container);

        $this->assertNotEmpty($container->getId());
        $this->assertInstanceOf('\Docker\Http\Stream\InteractiveStream', $stream);

        stream_set_blocking($stream->getSocket(), 0);

        $read   = [$stream->getSocket()];
        $write  = null;
        $expect = null;

        $stream->write("echo test\n");
        $data = "";
        do {
            $frame = $stream->receive(true);
            $data .= $frame['data'];
        } while (stream_select($read, $write, $expect, 1) > 0);

        $manager->stop($container, 1);

        $this->assertRegExp('#root@'.substr($container->getId(), 0, 12).':/\# echo test#', $data, $data);
    }

    public function _testCreateThrowsRightFormedException()
    {
        $container = new Container(['Image' => 'non-existent']);

        $manager = $this->getManager();

        try {
            $manager->create($container);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $this->assertTrue($e->hasResponse());
            $this->assertEquals("404", $e->getResponse()->getStatusCode());
            $this->assertContains('No such image: non-existent (tag: latest)', $e->getMessage());
        }
    }

    public function _testRunDefault()
    {
        $container = new Container(['Image' => 'ubuntu:precise', 'Cmd' => ['/bin/true']]);
        $manager = $this
            ->getMockBuilder('\Docker\Manager\ContainerManager')
            ->setMethods(['create', 'start', 'wait'])
            ->disableOriginalConstructor()
            ->getMock();

        $container->setExitCode(0);

        $manager->expects($this->once())
            ->method('create')
            ->with($this->isInstanceOf('\Docker\Container'))
            ->will($this->returnSelf());

        $manager->expects($this->once())
            ->method('start')
            ->with($this->isInstanceOf('\Docker\Container'))
            ->will($this->returnSelf());

        $manager->expects($this->once())
            ->method('wait')
            ->with($this->isInstanceOf('\Docker\Container'))
            ->will($this->returnSelf());

        $this->assertTrue($manager->run($container));
    }

    public function _testRunAttach()
    {
        $container = new Container(['Image' => 'ubuntu:precise', 'Cmd' => ['/bin/true']]);
        $manager = $this
            ->getMockBuilder('\Docker\Manager\ContainerManager')
            ->setMethods(['create', 'start', 'wait', 'attach'])
            ->disableOriginalConstructor()
            ->getMock();

        $response = $this->getMockBuilder('\GuzzleHttp\Message\Response')->disableOriginalConstructor()->getMock();
        $stream   = $this->getMockBuilder('\GuzzleHttp\Stream\Stream')->disableOriginalConstructor()->getMock();

        $container->setExitCode(0);
        $callback = function () {};

        $manager->expects($this->once())
            ->method('create')
            ->with($this->isInstanceOf('\Docker\Container'))
            ->will($this->returnSelf());

        $manager->expects($this->once())
            ->method('attach')
            ->with($this->isInstanceOf('\Docker\Container'), $this->equalTo($callback), $this->equalTo(true), $this->equalTo(true), $this->equalTo(true), $this->equalTo(true), $this->equalTo(true), $this->equalTo(null))
            ->will($this->returnValue($response));

        $manager->expects($this->once())
            ->method('start')
            ->with($this->isInstanceOf('\Docker\Container'))
            ->will($this->returnSelf());

        $response->expects($this->once())
            ->method('getBody')
            ->will($this->returnValue($stream));

        $manager->expects($this->once())
            ->method('wait')
            ->with($this->isInstanceOf('\Docker\Container'))
            ->will($this->returnSelf());

        $this->assertTrue($manager->run($container, $callback));
    }

    public function _testRunDaemon()
    {
        $container = new Container(['Image' => 'ubuntu:precise', 'Cmd' => ['/bin/true']]);
        $manager = $this
            ->getMockBuilder('\Docker\Manager\ContainerManager')
            ->setMethods(['create', 'start', 'wait'])
            ->disableOriginalConstructor()
            ->getMock();

        $container->setExitCode(0);

        $manager->expects($this->once())
            ->method('create')
            ->with($this->isInstanceOf('\Docker\Container'))
            ->will($this->returnSelf());

        $manager->expects($this->once())
            ->method('start')
            ->with($this->isInstanceOf('\Docker\Container'))
            ->will($this->returnSelf());

        $manager->expects($this->never())
            ->method('wait');

        $this->assertNull($manager->run($container, null, [], true));
    }

    public function _testAttach()
    {
        $container = new Container(['Image' => 'ubuntu:precise', 'Cmd' => ['/bin/bash', '-c', 'echo -n "output"']]);
        $manager = $this->getManager();

        $type   = 0;
        $output = "";

        $manager->create($container);
        $response = $manager->attach($container, function ($log, $stdtype) use (&$type, &$output) {
            $type = $stdtype;
            $output = $log;
        });
        $manager->start($container);

        $response->getBody()->getContents();

        $this->assertEquals(1, $type);
        $this->assertEquals('output', $output);
    }

    public function _testAttachStderr()
    {
        $container = new Container(['Image' => 'ubuntu:precise', 'Cmd' => ['/bin/bash', '-c', 'echo -n "error" 1>&2']]);
        $manager = $this->getManager();

        $type   = 0;
        $output = "";

        $manager->create($container);
        $response = $manager->attach($container, function ($log, $stdtype) use (&$type, &$output) {
            $type = $stdtype;
            $output = $log;
        });
        $manager->start($container);

        $response->getBody()->getContents();

        $this->assertEquals(2, $type);
        $this->assertEquals('error', $output);
    }

    /**
     * Not sure how to reliably test that we actually waited for the container
     * but this should at least ensure no exception is thrown
     */
    public function _testWait()
    {
        $container = new Container(['Image' => 'ubuntu:precise', 'Cmd' => ['/bin/sleep', '1']]);

        $manager = $this->getManager();
        $manager->run($container);
        $manager->wait($container);

        $runtimeInformations = $container->getRuntimeInformations();

        $this->assertEquals(0, $runtimeInformations['State']['ExitCode']);
    }

    /**
     * @expectedException GuzzleHttp\Exception\RequestException
     */
    public function _testWaitWithTimeout()
    {
        if (getenv('DOCKER_TLS_VERIFY')) {
            $this->markTestSkipped('This test failed when using ssl due to this bug : https://bugs.php.net/bug.php?id=41631');
        }

        $container = new Container(['Image' => 'ubuntu:precise', 'Cmd' => ['/bin/sleep', '2']]);

        $manager = $this->getManager();
        $manager->create($container);
        $manager->start($container);
        $manager->wait($container, 1);
    }

    public function _testTimeoutExceptionHasRequest()
    {
        if (getenv('DOCKER_TLS_VERIFY')) {
            $this->markTestSkipped('This test failed when using ssl due to this bug : https://bugs.php.net/bug.php?id=41631');
        }

        $container = new Container(['Image' => 'ubuntu:precise', 'Cmd' => ['/bin/sleep', '2']]);

        $manager = $this->getManager();
        $manager->run($container);

        try {
            $manager->wait($container, 1);
        } catch (RequestException $e) {
            $this->assertInstanceOf('Docker\\Http\\Request', $e->getRequest());
        }
    }


    public function _testLogs()
    {
        $container = new Container(['Image' => 'ubuntu:precise', 'Cmd' => ['echo', 'test']]);
        $manager = $this->getManager();
        $manager->run($container);
        $manager->stop($container);
        $logs = $manager->logs($container, false, true);
        $manager->remove($container);

        $this->assertGreaterThanOrEqual(1, count($logs));

        $logs = array_map(function ($value) {
            return $value['output'];
        }, $logs);

        $this->assertContains("test", implode("", $logs));
    }

    public function _testExec()
    {
        $manager = $this->getManager();
        $dockerFileBuilder = new ContextBuilder();
        $dockerFileBuilder->from('ubuntu:precise');
        $dockerFileBuilder->add('/daemon.sh', file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'script' . DIRECTORY_SEPARATOR . 'daemon.sh'));
        $dockerFileBuilder->run('chmod +x /daemon.sh');

        $this->getDocker()->build($dockerFileBuilder->getContext(), 'docker-php-restart-test', null, true, false, true);

        $container = new Container(['Image' => 'docker-php-restart-test', 'Cmd' => ['/daemon.sh']]);
        $manager->create($container);
        $manager->start($container);

        $type   = 0;
        $output = "";
        $execId = $manager->exec($container, ['/bin/bash', '-c', 'echo -n "output"']);

        $this->assertNotNull($execId);

        $response = $manager->execstart($execId, function ($log, $stdtype) use (&$type, &$output) {
            $type = $stdtype;
            $output = $log;
        });

        $response->getBody()->getContents();
        $manager->kill($container);

        $this->assertEquals(1, $type);
        $this->assertEquals('output', $output);
    }
}
