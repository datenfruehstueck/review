<?php

namespace ReviewOrganizer;

class Submission extends AbstractModel {
	protected $table = 'submission';
	public $event;
	public $created;
	public $title;
	public $abstract;
	public $keywords;
	public $pdf;
	public $organizer;
    public $is_revise;
    public $revised_submission;
    public $action_letter;
    public $decided;
    public $decision;
    public $decision_letter;
    public $authors;
    public $reviews;

    function get_authors($shortened = FALSE) {
        $authors = [];
        foreach ($this->authors->entries as $author) {
            $authors[] = $author->person->get_name_as_author($shortened);
        }
        return implode($shortened ? ', ' : '; ', $authors);
    }

    function get_email_list() {
        $emails = [];
        foreach ($this->authors->entries as $author) {
            $emails[] = $author->person->email;
        }
        return implode(',', $emails);
    }

    function contains_authoring_person($person) {
        foreach ($this->authors->entries as $author) {
            if($author->person->is_the_same_as($person)) {
                return TRUE;
            }
        }
        return FALSE;
    }

    function contains_reviewing_person($person) {
        foreach ($this->reviews->entries as $review) {
            if($review->reviewer->person->is_the_same_as($person)) {
                return TRUE;
            }
        }
        return FALSE;
    }

    function get_status() {
        $status = 'submitted';
        if($this->decision) {
            $status = $this->decision;
        } elseif ($this->reviews->count() > 0) {
            $status = 'awaiting decision';
            $openReviews = $this->reviews->get_entries_by_field('suggestion', '');
            if(count($openReviews) > 0) {
                $status = 'under review';
            }
        }
        return $status;
    }

    function get_keywords_as_array() {
        $keywords = [];
        foreach ($this->nicely_split_csv('keywords') as $keyword) {
            if(isset($this->config['keywords'][$keyword])) {
                $keywords[] = $this->config['keywords'][$keyword];
            }
        }
        return $keywords;
    }

    function inject_authors() {
        $this->authors = new ModelHandler($this->db, $this->config, 'author');
        $this->authors->sql_filter = '`submission` = '.intval($this->uid);
        $this->authors->sql_order = '`position` ASC';
        $this->authors->collect_entries();
    }

    function inject_reviews() {
        $this->reviews = new ModelHandler($this->db, $this->config, 'review');
        $this->reviews->sql_filter = '`submission` = '.intval($this->uid);
        $this->reviews->collect_entries();
    }
}
