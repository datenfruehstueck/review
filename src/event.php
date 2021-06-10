<?php

namespace ReviewOrganizer;

class Event extends AbstractModel {
	protected $table = 'event';
	public $title;
	public $description;
	public $cfp_pdf;
	public $cfp_deadline;
	public $review_deadline;
	public $link;
	public $requires_abstract;
	public $requires_pdf;
	public $make_submitters_reviewers;
	public $organizers;
	public $reviewers;
	public $submissions;

    function inject_organizers() {
        $this->organizers = new ModelHandler($this->db, $this->config, 'organizer');
        $this->organizers->sql_filter = '`event` = '.intval($this->uid);
        $this->organizers->collect_entries();
    }

    function inject_reviewers() {
        $this->reviewers = new ModelHandler($this->db, $this->config, 'reviewer');
        $this->reviewers->sql_filter = '`event` = '.intval($this->uid);
        $this->reviewers->collect_entries();
    }

    function inject_submissions() {
        $this->submissions = new ModelHandler($this->db, $this->config, 'submission');
        $this->submissions->sql_filter = '`event` = '.intval($this->uid);
        $this->submissions->collect_entries();
    }
}
