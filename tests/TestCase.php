<?php namespace tests;

use \Mockery as m;

abstract class TestCase extends \PHPUnit_Framework_TestCase
{
    protected function tearDown()
    {
        m::close();
    }
}