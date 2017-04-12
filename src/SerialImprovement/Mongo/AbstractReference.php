<?php

namespace SerialImprovement\Mongo;

abstract class AbstractReference extends AbstractDocument
{
    /**
     * Cache actual documents to a local property from fetch() call
     * return cached copy when called
     *
     * @return mixed
     */
    abstract public function resolve();

    /**
     * Find the actual document/s and return them
     *
     * @return mixed
     */
    abstract protected function fetch();
}
