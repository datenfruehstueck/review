<?php

namespace ReviewOrganizer;

class Review extends AbstractModel {
	protected $table = 'review';
	public $reviewer;
	public $submission;
	public $status;
	public $reviewed;
	public $suggestion;
	public $text_to_authors;
	public $text_to_organizers;
}
