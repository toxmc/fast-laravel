<?php
namespace FastLaravel\Http\Tracker\Saver;

use MongoDB\Database;
use FastLaravel\Http\Tracker\SaverInterface;

class Mongodb implements SaverInterface
{
    protected $collection;
    
    public function __construct(Database $db)
    {
        $this->collection = $db->results;
    }

    /**
     * Insert a profile run.
     *
     * @param array $profile The profile data to save.
     * @return \MongoDB\InsertOneResult
     */
    public function save($profile)
    {
        return $this->collection->insertOne($profile, array('w' => 0));
    }
}
