<?php

namespace ReviewOrganizer;

class Author extends AbstractModel {
    protected $table = 'author';
    public $person;
    public $submission;
    public $position;
}
