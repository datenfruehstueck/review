<?php

namespace ReviewOrganizer;

class Reviewer extends AbstractModel {
	protected $table = 'reviewer';
	public $person;
	public $event;
}
