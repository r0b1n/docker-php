<?php

namespace Docker\Tests\Manager;

use Docker\Tests\IsolatedTestCase;

use GuzzleHttp\Client;
use GuzzleHttp\Url;
use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Subscriber\History;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;


class ImageManagerIsolatedTest extends IsolatedTestCase
{

    /**
     * Return a container manager
     *
     * @return \Docker\Manager\ImageManager
     */
    private function getManager()
    {
        return $this->getDocker()->getImageManager();
    }

    private function getMockedManager($list, $clientOptions = [])
    {
        $manager = new \Docker\Manager\ImageManager($this->getMockedClient($list, $clientOptions));

        return $manager;
    }

    public function findProvider()
    {
        return [
            [ $this->fromFile('/data/image/01-dockerImage.json'), 'test', 'foo', 'b750fe79269d2ec9a3c593ef05b4332b1d1a02a62b4accb2c21d589ff2f5f2dc' ],
            [ $this->fromFile('/data/image/01-dockerImage.json'), 'repo_here', 'sometag', 'b750fe79269d2ec9a3c593ef05b4332b1d1a02a62b4accb2c21d589ff2f5f2dc' ]
        ];
    }

    public function findNotFoundProvider()
    {
        return [
            ['repo', 'latest'],
            ['some-repo', 'some-tag']
        ];
    }

    public function findAllProvider()
    {
        return [
            [ $this->fromFile('/data/image/02-dockerFindAll.json') ]
        ];
    }

    public function historyProvider()
    {
        return [
            [ 
                $this->fromFile('/data/image/05-dockerHistory.json'),
                'somerepo',
                'sometag'
            ]
        ];
    }

    public function removeProvider()
    {
        return [
            [
                $this->fromFile('/data/image/01-dockerImage.json'),
                $this->fromFile('/data/image/03-dockerRemoved.json'),
                'somerepo',
                'sometag',
                'b750fe79269d2ec9a3c593ef05b4332b1d1a02a62b4accb2c21d589ff2f5f2dc'
            ]
        ];
    }

    public function pullProvider()
    {
        return [
            [ 
                $this->fromFile('/data/image/04-dockerPull.json'), 
                $this->fromFile('/data/image/01-dockerImage.json'),
                'somerepo',
                'sometag'
            ]
        ];
    }

    public function tagProvider()
    {
        return [
            [ 
                'somerepo',
                'sometag',
                'docker-php/unit-test',
                'new_sometag'
            ]
        ];
    }

    public function searchProvider()
    {
        return [
            [ 
                'sshd',
                $this->fromFile('/data/image/06-dockerSearch.json'),
                [
                    [
                        'name' => 'wma55/u1210sshd'
                    ],
                    [
                        'name' => 'jdswinbank/sshd'
                    ],
                    [
                        'name' => 'vgauthier/sshd'
                    ]
                ]
            ]
        ];
    }

    /**
     * @dataProvider findProvider
     */
    public function testFind($json, $repo, $tag, $id)
    {
        $manager = $this->getMockedManager([
            $json  // Use a response string
        ]);

        $image   = $manager->find($repo, $tag);

        $this->assertEquals($repo, $image->getRepository());
        $this->assertEquals($tag, $image->getTag());
        $this->assertEquals($id, $image->getId());

        // check request
        $history = $this->getRequestsHistory();
        $this->assertEquals(1, $history->count());
        $this->assertEquals("GET", $history->getLastRequest()->getMethod());
        $this->assertEquals("/images/".rawurlencode($repo)."%3A".rawurlencode($tag)."/json", $history->getLastRequest()->getPath());
    }

    /**
     * @dataProvider findNotFoundProvider
     */
    public function testFindInexistant($repo, $tag)
    {
        $manager = $this->getMockedManager([
            new Response(404)
        ]);
        try {
            $manager->find($repo, $tag);
        }
        catch (\Docker\Exception\ImageNotFoundException $e) {
            $this->assertEquals("Image not found: \"{$repo}:{$tag}\"", $e->getMessage());
        }

        // check request is correct
        $history = $this->getRequestsHistory();
        $this->assertEquals(1, $history->count());
        $this->assertEquals("GET", $history->getLastRequest()->getMethod());
        $this->assertEquals("/images/".rawurlencode($repo)."%3A".rawurlencode($tag)."/json", $history->getLastRequest()->getPath());
    }

    /**
     * @dataProvider pullProvider
     */
    public function testPull($pull, $inspect, $repo, $tag)
    {
        $factory = $this
            ->getMockBuilder('\GuzzleHttp\Message\MessageFactory')
            ->setMethods(['add_callback', 'add_wait'])
            ->getMock();

        $manager = $this->getMockedManager([
            $pull,
            $inspect
        ], [
            'message_factory' => $factory
        ]);
        
        
        $image = $manager->pull($repo, $tag);

        $this->assertEquals($repo, $image->getRepository());
        $this->assertEquals($tag, $image->getTag());
        $this->assertNotNull($image->getId());


        $history = $this->getRequestsHistory();
        $this->assertEquals(2, $history->count());

        $requests = $history->getRequests();   

        // first - create request
        $this->assertEquals("POST", $requests[0]->getMethod());
        $this->assertEquals("/images/create", $requests[0]->getPath());
        $requests[0]->getQuery()->hasKey('fromImage') && $this->assertEquals($repo, $requests[0]->getQuery()['fromImage']);
        $requests[0]->getQuery()->hasKey('tag') && $this->assertEquals($tag, $requests[0]->getQuery()['tag']);
        // TODO: add more fields to check

        // second - inspect request
        $this->assertEquals("GET", $requests[1]->getMethod());
        $this->assertEquals("/images/".rawurlencode($repo)."%3A".rawurlencode($tag)."/json", $requests[1]->getPath());
    }

    /**
     * @dataProvider findAllProvider
     */
    public function testFindAll($json)
    {
        $manager = $this->getMockedManager([
            $json  // Use a response string
        ]);
        $result = $manager->findAll();
        $this->assertInternalType('array', $result);
        $this->assertGreaterThanOrEqual(1, count($result));

        // check request
        $history = $this->getRequestsHistory();
        $this->assertEquals(1, $history->count());
        $this->assertEquals("GET", $history->getLastRequest()->getMethod());
        $this->assertEquals("/images/json", $history->getLastRequest()->getPath());
        $history->getLastRequest()->getQuery()->hasKey('all') && $this->assertContains($history->getLastRequest()->getQuery()['all'], $this->_booleanAllowed);
    }

    /**
     * @dataProvider removeProvider
     */
    public function testRemove($jsonExists, $jsonRemoved, $repo, $tag, $imageId)
    {
        $manager = $this->getMockedManager([
            $jsonRemoved,  // Use a response string
        ]);


        $image = new \Docker\Image($repo, $tag);
        // remove this image
        $manager->remove($image, true);

        
        // check requests
        $history = $this->getRequestsHistory();
        $this->assertEquals(1, $history->count());

        $requests = $history->getRequests();

        // delete request
        $this->assertEquals("DELETE", $requests[0]->getMethod());
        $this->assertEquals("/images/".rawurlencode($repo)."%3A".rawurlencode($tag)."", $requests[0]->getPath()); // TODO: review this
        //$this->assertContains($requests[0]->getQuery()['force'], $this->_booleanAllowed);
        //$this->assertContains($requests[0]->getQuery()['noprune'], $this->_booleanAllowed);
    }

    public function testRemoveImages()
    {
        $containers = ['ubuntu:precise', '69c02692b0c1'];

        $manager = $this->getMockedManager([
            new Response(200),
            new Response(200)
        ]);

        $manager->removeImages($containers);

        $history = $this->getRequestsHistory();
        $this->assertEquals(2, $history->count());
        $requests = $history->getRequests();


        $this->assertEquals("DELETE", $requests[0]->getMethod());
        $this->assertEquals("/images/ubuntu%3Aprecise", $requests[0]->getPath());
        // TODO: test additional options


        $this->assertEquals("DELETE", $requests[1]->getMethod());
        $this->assertEquals("/images/69c02692b0c1", $requests[1]->getPath());
        //$requests[1]->getQuery()->hasKey('force') && $this->assertContains($requests[1]->getQuery()['force'], $this->_booleanAllowed);
        // TODO: test additional options


    }

    /**
     * @dataProvider searchProvider
     */
    public function testSearch($searchTerm, $json, $imagesData)
    {
        $manager = $this->getMockedManager([
            $json
        ]);

        $result = $manager->search($searchTerm);
        $this->assertEquals(count($imagesData), count($result));

        foreach ($imagesData as $k => $image) {
            $this->assertEquals($result[$k]['name'], $image['name']);
        }
        

        $history = $this->getRequestsHistory();
        $this->assertEquals(1, $history->count());
        $requests = $history->getRequests();

        $this->assertEquals("GET", $requests[0]->getMethod());
        $this->assertEquals("/images/search", $requests[0]->getPath());
        $this->assertEquals($searchTerm, $requests[0]->getQuery()['term']);
    }

    /**
     * @dataProvider tagProvider
     */
    public function testTag($repo, $tag, $newRepo, $newTag)
    {
        $manager = $this->getMockedManager([
            new Response(201),
        ]);
        $image = new \Docker\Image($repo, $tag);

        $manager->tag($image, $newRepo, $newTag);

        $this->assertEquals($newRepo, $image->getRepository());
        $this->assertEquals($newTag, $image->getTag());

        
        // test requests
        $history = $this->getRequestsHistory();
        $this->assertEquals(1, $history->count());
        $requests = $history->getRequests();


        $this->assertEquals("POST", $requests[0]->getMethod());
        $this->assertEquals("/images/{$image->getId()}/tag", $requests[0]->getPath());
        $this->assertEquals($newRepo, $requests[0]->getQuery()['repo']);
        $this->assertEquals($newTag, $requests[0]->getQuery()['tag']);
    }

    /**
     * @dataProvider historyProvider
     */
    public function testHistory($jsonHistory, $repo, $tag)
    {
        $manager = $this->getMockedManager([
            $jsonHistory
        ]);

        $image = new \Docker\Image($repo, $tag);
        $imageHistory = $manager->history($image);

        // check history correct
        $this->assertEquals(2, count($imageHistory));
        $this->assertEquals('/bin/bash', $imageHistory[0]['CreatedBy']);

        // check requests
        $history = $this->getRequestsHistory();
        $this->assertEquals(1, $history->count());
        $requests = $history->getRequests();

        // get history request
        $this->assertEquals("GET", $requests[0]->getMethod());
        $this->assertEquals("/images/".rawurlencode($repo)."%3A".rawurlencode($tag)."/history", $requests[0]->getPath());
    }
}
