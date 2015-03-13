<?php

namespace Docker\Tests;

use Docker\Docker;
use GuzzleHttp\Client;

use GuzzleHttp\Subscriber\Mock;
use GuzzleHttp\Subscriber\History;



class IsolatedTestCase extends TestCase
{
    protected $_booleanAllowed = ['1', 'true', 'True', '0', 'false', 'False'];
    protected $_booleanTrue = ['1', 'true', 'True'];
    protected $_booleanFalse = ['0', 'false', 'False'];

    private $_history;
    private $_mock;

    protected function fromFile($name) {
    	return file_get_contents(dirname(__FILE__) . $name);
    }

    protected function getMockedClient($list, $clientOptions = [])
    {
        $client = new Client($clientOptions);
        $this->_history = new History();
        $this->_mock = new Mock($list);

        $client->getEmitter()->attach($this->_mock);
        $client->getEmitter()->attach($this->_history);

        return $client;
    }

    protected function getRequestsHistory()
    {
        return $this->_history;
    }
}
