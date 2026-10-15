<?php

namespace NineteenNinetyFour\Reviews\Tests\Feature;

use NineteenNinetyFour\Reviews\Tests\TestCase;

class DatabaseRepositoryTest extends TestCase
{
    use RepositoryContract;

    protected string $driver = 'database';
}
