<?php

namespace ReviewOrganizer;
require_once('abstractmodel.php');
require_once('modelhandler.php');
require_once('database.php');

class System {
    protected $config;
    protected $db;
    protected $person;
    protected $event;

    function __construct(&$config) {
        $this->config = $config;
        $this->db = new Database($config);
        $this->person = NULL;
        $this->event = NULL;
    }

    function debug($var) {
        if(is_string($var) || is_numeric($var)) {
            echo '<code>'.$var.'</code><br>';
        } elseif(is_bool($var)) {
            echo '<code>'.intval($var).'</code><br>';
        } elseif(is_array($var)) {
            echo '<pre>'.print_r($var, TRUE).'</pre><br>';
        } else {
            echo '<pre>';
            var_dump($var);
            echo '</pre><br>';
        }
    }

    function run() {
        //collect table names
        $this->config['database']['tables'] = [];
        while($row = $this->db->fetch_row('SHOW TABLES')) {
            $table = array_pop($row);
            $this->config['database']['tables'][] = $table;
            require_once($table.'.php');
        }

        //preprocess URL
        $url = trim($_SERVER['REQUEST_URI']);
        $url = str_replace('?'.$_SERVER['QUERY_STRING'], '', $url);
        if(substr($url, 0, 1) == '/') {
            $url = substr($url, 1);
        }
        if(substr($url, -1) == '/') {
            $url = substr($url, 0, strlen($url)-1);
        }

        /**
         * no event => 404
         * event
         * - not logged in => submission invitation
         * - logged in (default to first applying)
         *   > is organizer of the current event => show submissions
         *   > has reviews => show review tasks
         *   > has submission => show submission status
         */

        //extract event from URL (and remove from URL)
        $event_link = explode('/', $url, 2)[0];
        $event_row = $this->db->fetch_row('SELECT * FROM `event` WHERE `link` = \''.$this->db->escape($event_link).'\'');
        if($event_row) {
            $this->event = new Event($this->db, $this->config, $event_row);
            $this->event->inject_organizers();
            $this->event->inject_reviewers();
            $this->event->inject_submissions();
            if($event_link == $url) {
                $url = '';
            } else {
                $url = str_replace($event_link . '/', '', $url);
            }
        }

        //catch logged-in person
        if(isset($_SESSION['person'])) {
            $personHandler = new ModelHandler($this->db, $this->config, 'person');
            $personHandler->sql_filter = '`uid` = '.intval($_SESSION['person']);
            $personHandler->collect_entries();
            if($personHandler->count() == 1) {
                $this->person = $personHandler->get(0);
                $this->person->inject_submissions();
                $this->person->inject_reviews();
                $this->person->inject_organizers();
            }
        }

        //if no event is found allow registration or display error
        if($this->event === NULL) {
            if(isset($_GET['register'])) {
                $message = '';
                if($_SERVER['REQUEST_METHOD'] == 'POST') {
                    if($_POST['salutation'] != '' && $_POST['firstname'] != '' && $_POST['lastname'] != '' && $_POST['email'] != '' && $_POST['title'] != '' && $_POST['link'] != '') {
                        $registeredPerson = $this->get_or_create_person($_POST['email'], $_POST['salutation'], $_POST['firstname'], $_POST['lastname']);
                        if($registeredPerson != NULL) {
                            $link = urlencode(strtolower(trim($_POST['link'])));
                            $eventHandler = new ModelHandler($this->db, $this->config, 'event');
                            $eventHandler->collect_entries();
                            if(count($eventHandler->get_entries_by_field('link', $link)) > 0) {
                                $this->redirect($link.'/profile');
                            } else {
                                $registeredEvent = $eventHandler->add(new Event($this->db, $this->config, [
                                    'title' => trim($_POST['title']),
                                    'link' => $link
                                ]));
                                if ($registeredEvent) {
                                    $registeredPerson->inject_organizers();
                                    if ($registeredPerson->organizers->add(new Organizer($this->db, $this->config, [
                                        'person' => $registeredPerson,
                                        'event' => $registeredEvent
                                    ]))) {
                                        $this->redirect($registeredEvent->link.'/profile');
                                    } else {
                                        $message = 'Event successfully created but you could not be added as an organizer. Please inform the webmaster.';
                                    }
                                } else {
                                    $message = 'Event could not be created.';
                                }
                            }
                        } else {
                            $message = 'Person could not be created.';
                        }
                    } else {
                        $message = 'Please fill in all details and resubmit.';
                    }
                }
                $salutation_options = '';
                foreach($this->config['salutation'] as $value => $display) {
                    $salutation_options .= '<option value="'.$value.'">'.$display.'</option>';
                }
                $this->output('register_form', [
                    'base_url' => $this->config['base_url'],
                    'salutation_options' => $salutation_options,
                    'message' => $message,
                    'message_hidden' => $message == '' ? 'd-none' : ''
                ]);
            } else {
                $this->output('error', ['error' => 'Urgs, no event given or event not found. Are you sure that the URL is correct?']);
                return FALSE;
            }
        }

        //explicit URL routing
        switch ($url) {
            case 'cfp':
                return $this->route_cfp();
                break;
            case 'forgot_password':
                if(isset($_GET['id'])) {
                    $hash_part = substr($_GET['id'], 0, 15);
                    $uid = intval(str_replace($hash_part, '', $_GET['id']));
                    $personHandler = new ModelHandler($this->db, $this->config, 'person');
                    $personHandler->sql_filter = '`uid` = '.$uid;
                    $personHandler->collect_entries();
                    if($personHandler->count() > 0) {
                        $personWithForgottenEmail = $personHandler->get(0);
                        if (substr($personWithForgottenEmail->password_hash, 15, 15) == $hash_part) {
                            $password = $personWithForgottenEmail->generate_password();
                            $personWithForgottenEmail->update();
                            $personWithForgottenEmail->send_mail('New password generated for ' . $this->event->title,
                                'a new password has just been created for this email address:' . chr(10) . chr(10) .
                                $password . chr(10) . chr(10));
                            $this->output('success', ['success' => 'A new password has been sent to your email address.']);
                            return TRUE;
                        }
                    }
                }
                break;
            case 'review':
            case 'reviews':
                return $this->route_reviews();
                break;
            case 'submission':
            case 'submissions':
                if(isset($_GET['id'])) {
                    return $this->route_submission(intval($_GET['id']));
                } else {
                    if($this->is_current_user_an_organizer()) {
                        return $this->route_organizer_submissions();
                    } else {
                        return $this->route_author_submissions();
                    }
                }
                break;
            case 'submission/decide':
                if(isset($_GET['id'])) {
                    return $this->route_decide(intval($_GET['id']));
                } else {
                    return $this->route_organizer_submissions();
                }
                break;
            case 'submission/cancel_review':
                if(isset($_GET['id'])) {
                    return $this->route_cancel_review(intval($_GET['id']));
                } else {
                    return $this->route_organizer_submissions();
                }
                break;
            case 'profile':
            case 'event':
                if(isset($_GET['id'])) {
                    return $this->route_event(intval($_GET['id']));
                } else {
                    return $this->route_profile();
                }
                break;
            case 'logout':
                unset($_SESSION['person']);
                $this->redirect('cfp');
                break;
        }

        //implicit URL fallback routing
        if($this->person === NULL) {
            $this->redirect('cfp');
        } else {
            if(strpos($this->config['upload_dir'], $url) == 0 && is_readable($url)) {
                $submissionHandler = new ModelHandler($this->db, $this->config, 'submission');
                $submissionHandler->sql_filter = '`pdf` = \''.$url.'\'';
                $submissionHandler->collect_entries();
                if($submissionHandler->count() == 1) {
                    $submission = $submissionHandler->get(0);
                    //organizers are allowed to see the pdf
                    //authors are allowed to see the pdf
                    //reviewers are allowed to see the pdf
                    $submission->event->inject_organizers();
                    $submission->inject_authors();
                    if(count($submission->event->organizers->get_entries_by_field('person', $this->person->uid)) > 0 ||
                        count($submission->authors->get_entries_by_field('person', $this->person->uid)) > 0 ||
                        count($this->person->reviews->get_entries_by_field('submission', $submission->uid)) > 0) {

                        header('Content-type: application/pdf');
                        header('Content-Disposition: inline; filename='.str_replace($this->config['upload_dir'], '', $url));
                        @readfile($url);
                        return TRUE;
                    }
                }
            } elseif($this->is_current_user_an_organizer() || count($this->person->reviews->get_entries_by_field('status', 'done')) > 0) {
                $this->redirect('reviews');
            } else {
                if ($this->person->submissions->count() > 0) {
                    $this->redirect('submission/'.$this->person->submissions->get(0)->uid);
                } else {
                    $this->redirect('submissions');
                }
            }
        }
        return FALSE;
    }

    protected function redirect($route) {
        $url = '/';
        if($this->event !== NULL) {
            $url .= $this->event->link.'/';
        }
        $url .= $route;
        header('Location: '.$url);
        exit();
    }

    protected function refresh() {
        header('Location: '.$_SERVER['REQUEST_URI']);
        exit();
    }

    protected function output($template, array $marker = [], $active = '', $title = 'Review System') {
        if($this->event !== NULL) {
            $title = $this->event->title.' '.$title;
        }
        if($active == '') {
            $active = $template;
        }
        echo $this->replace($this->person === NULL ? '_logged-out' : '_logged-in', [
            'title' => $title,
            'content' => $this->replace($template, $marker),
            'event' => $this->event === NULL ? '' : $this->event->link,
            'event_title' => $this->event === NULL ? '' : $this->event->title,
            'event_hidden' => $this->event === NULL ? 'd-none' : '',
            'events_hidden' => $this->person !== NULL && $this->person->organizers->count() > 0 ? '' : 'd-none',
            'active_cfp' => $active == 'cfp' ? 'active' : '',
            'active_submissions' => $active == 'submissions' || $active == 'submission' ? 'active' : '',
            'active_reviews' => $active == 'reviews' || $active == 'review' ? 'active' : '',
            'active_events' => $active == 'profile' || $active == 'event' ? 'active' : ''
        ]);
    }

    protected function replace($template, array $marker = []) {
        $template = file_get_contents('html/'.$template.'.html');
        foreach($marker as $mark => $value) {
            $template = str_replace('{{'.$mark.'}}', $value, $template);
        }
        return $template;
    }

    protected function get_or_create_person($email, $salutation = '', $firstname = '', $lastname = '', $keywords = '') {
        $personHandler = new ModelHandler($this->db, $this->config, 'person');
        $personHandler->collect_entries();
        $email = strtolower(trim($email));
        $existingPersons = $personHandler->get_entries_by_field('email', $email);
        if(count($existingPersons) > 0) {
            return $existingPersons[0];
        } else {
			if(strlen($firstname) > 50 || strlen($lastname) > 50 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
				return NULL;
			}
            $person = $personHandler->add(new Person($this->db, $this->config, [
                'email' => $email,
                'salutation' => $salutation,
                'firstname' => trim($firstname),
                'lastname' => trim($lastname),
                'keywords' => $keywords,
                'password_hash' => ''
            ]));
            if($person) {
                $password = $person->generate_password();
                $person->update();
                $person->send_mail('User created for '.$this->event->title,
                    'a new user has just been created with the following credentials:'.chr(10).chr(10).
                    'Email: '.$person->email.chr(10).
                    'Password: '.$password.chr(10).chr(10).
                    $this->config['base_url'].$this->event->link.'/profile'.chr(10).chr(10).
                    'This email has been sent automatically. If this was not expected, please contact the webmaster at '.$this->config['mail']['from_email'].'.'.chr(10));
                return $person;
            }
        }
        return NULL;
    }

    protected function create_submission() {
        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            if($_POST['title'] != '' && ($_POST['abstract'] != '' || !$this->event->requires_abstract)) {
                $submissionHandler = new ModelHandler($this->db, $this->config, 'submission');
                $revised_submission = NULL;
                $action_letter = '';
                if(isset($_POST['revised_submission'])) {
                    $revised_submission = $this->event->submissions->get_entry_by_uid(intval($_POST['revised_submission']));
                    $action_letter = trim($_POST['action_letter']);
                }
                $submission = $submissionHandler->add(new Submission($this->db, $this->config, [
                    'event' => $this->event,
                    'created' => time(),
                    'title' => $_POST['title'],
                    'abstract' => $_POST['abstract'],
                    'keywords' => implode(',', $_POST['keywords']),
                    'pdf' => '',
                    'organizer' => NULL,
                    'is_revise' => $revised_submission ? 1 : 0,
                    'revised_submission' => $revised_submission,
                    'action_letter' => $action_letter
                ]));
                if($submission) {
                    //upload PDF
                    if ($this->event->requires_pdf) {
                        if (isset($_FILES['pdf']) &&
                            $_FILES['pdf']['tmp_name'] != '' &&
                            $_FILES['pdf']['error'] == UPLOAD_ERR_OK &&
                            $_FILES['pdf']['type'] == 'application/pdf') {
                            $submission->pdf = $this->config['upload_dir'] . $submission->uid . '.pdf';
                            if (move_uploaded_file($_FILES['pdf']['tmp_name'], $submission->pdf)) {
                                $submission->update();
                            } else {
                                $this->output('error', ['error' => 'Uploading your PDF did not work properly.']);
                            }
                        } else {
                            $this->output('error', ['error' => 'PDF is missing.']);
                        }
                    }
                    //add author(s)
                    if (isset($_POST['author_firstname'])) {
                        $submission->inject_authors();
                        for ($i = 0; $i < count($_POST['author_firstname']); $i++) {
                            $person = $this->get_or_create_person(
                                $_POST['author_email'][$i],
                                $_POST['author_salutation'][$i],
                                $_POST['author_firstname'][$i],
                                $_POST['author_lastname'][$i],
                                $submission->keywords
                            );
                            if($person) {
                                $submission->authors->add(new Author($this->db, $this->config, [
                                    'person' => $person,
                                    'submission' => $submission,
                                    'position' => $i+1
                                ]));
                                if ($this->event->make_submitters_reviewers) {
                                    $already_exists = FALSE;
                                    foreach($this->event->reviewers->entries as $reviewer) {
                                        if ($reviewer->person->uid == $person->uid &&
                                            $reviewer->event->uid == $this->event->uid) {

                                            $already_exists = TRUE;
                                            break;
                                        }
                                    }
                                    if(!$already_exists) {
                                        $this->event->reviewers->add(new Reviewer($this->db, $this->config, [
                                            'person' => $person,
                                            'event' => $this->event
                                        ]));
                                    }
                                }
                            } else {
                                $this->output('error', ['error' => 'An error occurred during author creation.']);
                            }
                        }
                        foreach ($submission->authors->entries as $author) {
                            $author->person->send_mail('New submission received for ' . $this->event->title,
                                'the following submission has just been uploaded for ' . $this->event->title . '.' . chr(10) . chr(10) .
                                $submission->title . chr(10) .
                                $submission->get_authors() . chr(10) . chr(10) .
                                'You can check the status of this submission here:' . chr(10) .
                                $this->config['base_url'] . $this->event->link . '/submissions?id=' . $submission->uid . chr(10));
                        }
                        foreach ($this->event->organizers->entries as $organizer) {
                            $organizer->person->send_mail('New submission received for ' . $this->event->title,
                                'the following submission has just been uploaded for ' . $this->event->title . '.' . chr(10) . chr(10) .
                                $submission->title . chr(10) .
                                $submission->get_authors() . chr(10) . chr(10) .
                                $this->config['base_url'] . $this->event->link . '/submissions?id=' . $submission->uid . chr(10));
                        }
                        return TRUE;
                    } else {
                        $this->output('error', ['error' => 'Author is missing.']);
                    }
                } else {
                    $this->output('error', ['error' => 'An error occurred during submission creation.']);
                }
            } else {
                $this->output('error', ['error' => 'Title/Abstract are missing.']);
            }
        }
        return FALSE;
    }

    protected function assign_review($submission_uid, $reviewer_uid) {
        if($submission = $this->event->submissions->get_entry_by_uid(intval($submission_uid))) {
            if(!$submission->organizer) {
                $submission->organizer = $this->get_current_user_organizer();
                $submission->update();
            }
            $submission->inject_reviews();
            if($reviewer = $this->event->reviewers->get_entry_by_uid(intval($reviewer_uid))) {
                foreach($submission->reviews->get_entries_by_field('reviewer', $reviewer) as $review) {
                    if($review->status != 'cancelled') {
                        return FALSE;
                    }
                }
                if($submission->reviews->add(new Review($this->db, $this->config, [
                    'reviewer' => $reviewer,
                    'submission' => $submission,
                    'status' => 'assigned',
                    'suggestion' => '',
                    'text_to_authors' => '',
                    'text_to_organizers' => ''
                ]))) {
                    $reviewer->person->send_mail('New review assigned for ' . $this->event->title,
                        'you have just been assigned a new review of the following submission for ' . $this->event->title . '.' . chr(10) . chr(10) .
                        $submission->title.chr(10).chr(10).
                        'Read the whole submission and submit your review by '.date($this->config['date_format'], $this->event->review_deadline).' under the following link:'.chr(10).
                        $this->config['base_url'] . $this->event->link . '/reviews' . chr(10));
                    return TRUE;
                }
            }
        }
        return FALSE;
    }

    protected function route_cfp() {
        if($_SERVER['REQUEST_METHOD'] == 'POST') {
            if($this->create_submission()) {
                $this->output('success', ['success' => 'Thank you for this submission, which has successfully been uploaded.']);
                return TRUE;
            }
        } else {
            $organizers = [];
            foreach ($this->event->organizers->entries as $organizer) {
                $organizers[] = $organizer->person->get_name_as_author();
            }
            $salutation_options = '';
            foreach($this->config['salutation'] as $value => $display) {
                $salutation_options .= '<option value="'.$value.'">'.$display.'</option>';
            }
            $keywords_options = '';
            foreach($this->config['keywords'] as $value => $display) {
                $keywords_options .= '<label class="form-check-label d-block"><input type="checkbox" name="keywords[]" value="'.$value.'"> '.$display.'</label>';
            }
            $this->output('cfp', [
                'link' => $this->event->link,
                'title' => $this->event->title,
                'description' => nl2br($this->event->description),
                'organizers' => implode('; ', $organizers),
                'organizers_hidden' => count($organizers) > 0 ? '' : 'd-none',
                'cfppdf' => $this->event->cfp_pdf,
                'cfppdf_hidden' => is_file($this->event->cfp_pdf) ? '' : 'd-none',
                'cfp_deadline' => date($this->config['date_format'], $this->event->cfp_deadline),
                'cfp_hidden' => $this->event->cfp_deadline < time() && $this->event->cfp_deadline > 0 ? 'd-none' : '',
                'salutation_options' => $salutation_options,
                'keywords_options' => $keywords_options,
                'abstract_hidden' => $this->event->requires_abstract ? '' : 'd-none',
                'pdf_hidden' => $this->event->requires_pdf ? '' : 'd-none'
            ]);
            return TRUE;
        }
        return FALSE;
    }

    protected function require_login() {
        if($this->person !== NULL) {
            return TRUE;
        } else {
            $message = '';
            if(isset($_POST['email'])) {
                $email = strtolower(trim($_POST['email']));
                $personHandler = new ModelHandler($this->db, $this->config, 'person');
                $personHandler->collect_entries();
                $persons = $personHandler->get_entries_by_field('email', $email);
                if(count($persons) > 0) {
                    $person = $persons[0];
                    if(isset($_POST['password']) && $_POST['action'] == 'Login') {
                        if ($person->check_password($_POST['password'])) {
                            $_SESSION['person'] = $person->uid;
                            $this->refresh();
                        }
                    } elseif ($_POST['action'] == 'Forgot my password') {
                        $person->send_mail('Password request for '.$this->event->title,
                            'we have received a request that your password has been forgotten. If this was you and on purpose, you can click the following link to generate a new password:'.chr(10).chr(10).
                            $this->config['base_url'].$this->event->link.'/forgot_password?id='.substr($person->password_hash, 15, 15).$person->uid.chr(10).chr(10).
                            'If this was not you or not on purpose, just ignore this email.'.chr(10));
                        $message = 'Link to reset password has been sent.';
                    }
                } else {
                    $message = 'User not found or wrong password.';
                }
            }
            $this->output('login_form', [
                'redirect_url' => $_SERVER['REQUEST_URI'],
                'message' => $message,
                'message_hidden' => $message == '' ? 'd-none' : ''
                ]);
            return FALSE;
        }
    }

    protected function get_current_user_organizer() {
        foreach ($this->event->organizers->entries as $organizer) {
            if ($organizer->person->is_the_same_as($this->person)) {
                return $organizer;
            }
        }
        return NULL;
    }

    protected function is_current_user_an_organizer() {
        if($organizer = $this->get_current_user_organizer()) {
            if ($organizer->person->is_the_same_as($this->person)) {
                return TRUE;
            }
        }
        return FALSE;
    }

    protected function route_reviews() {
        if($this->require_login()) {
            $message = '';
            $comments = '';

            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                $review = $this->person->reviews->get_entry_by_uid(intval($_POST['review']));
                if(!$review) {
                    $message = 'Review could not be found.';
                }
                if($review && isset($_POST['suggestion']) && isset($_POST['text_to_authors']) &&
                    isset($this->config['decision'][$_POST['suggestion']])) {

                    $review->status = 'reviewed';
                    $review->reviewed = time();
                    $review->suggestion = $_POST['suggestion'];
                    $review->text_to_authors = trim($_POST['text_to_authors']);
                    $review->text_to_organizers = trim($_POST['text_to_organizers']);
                    if($review->update()) {
                        $review->submission->organizer->person->send_mail('Review submitted for ' . $this->event->title,
                            $this->person->get_name_as_salutation().' just submitted a review for the following submission for ' . $this->event->title . '.' . chr(10) . chr(10) .
                                $review->submission->title.chr(10).chr(10).
                                $this->config['base_url'] . $this->event->link . '/submissions' . chr(10)
                        );
                        $this->refresh();
                    } else {
                        $message = 'Error while saving review. Please try again.';
                        $comments = $_POST['text_to_authors'];
                    }
                } else {
                    $message = 'Suggestion and comments to the authors must be filled in when submitting a review.';
                    $comments = $_POST['text_to_authors'];
                }
            }

            $reviews = '';
            $link_submission = $this->config['base_url'].$this->event->link.'/submission?id=';
            foreach ($this->person->reviews->entries as $review) {
                if($review->status == 'assigned') {
                    $reviews .= '<tr class="' . ($review->status == 'assigned' ? 'table-info' : '') . '">' .
                        '<td><a href="' . $link_submission . $review->submission->uid . '">' . $review->submission->title . '</a></td>' .
                        '<td>' . $review->submission->organizer->person->get_name_as_salutation() . '</td>' .
                        '<td><em>due on ' . date($this->config['date_format'], $this->event->review_deadline) . '</em></td>' .
                        '<td><a class="btn btn-outline-primary btn-sm" href="#" data-toggle="modal" data-target="#modal_review" data-review="' . $review->uid . '">enter review</a></td>' .
                        '</tr>';
                }
            }

            $suggestion_options = '';
            foreach($this->config['decision'] as $value => $display) {
                $suggestion_options .= '<option value="'.$value.'">'.$display.'</option>';
            }

            $this->output('reviews', [
                'table_reviews' => $reviews,
                'text_to_authors_prefill' => $comments,
                'suggestion_options' => $suggestion_options,
                'message' => $message,
                'message_hidden' => $message == '' ? 'd-none' : ''
            ]);
            return TRUE;
        }
        return FALSE;
    }

    protected function route_author_submissions() {
        if($this->require_login()) {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                if ($this->create_submission()) {
                    $this->refresh();
                    return TRUE;
                }
            }

            $table_archive = '';
            $table_submissions = '';
            $link_submission = $this->config['base_url'].$this->event->link.'/submission?id=';
            foreach ($this->person->submissions->entries as $submission) {
                $submission->inject_authors();
                $submission->inject_reviews();
                $revision_badge = '';
                if($submission->is_revise) {
                    $revised_submission = $this->event->submissions->get_entry_by_uid($submission->revised_submission);
                    $revision_badge = ' <a href="'.$link_submission.$revised_submission->uid.'" class="badge badge-primary" title="This is a revision to a version submitted on '.date($this->config['date_format'], $revised_submission->created).'">R<small>&</small>R</a>';
                }
                $revision_button = '';
                if(strpos($submission->decision, 'Revis') !== FALSE) {
                    $revision_authors = [];
                    foreach($submission->authors->entries as $author) {
                        $revision_authors[] = [
                            'salutation' => $author->person->salutation,
                            'firstname' => $author->person->firstname,
                            'lastname' => $author->person->lastname,
                            'email' => $author->person->email
                        ];
                    }
                    $revision_details = htmlspecialchars(json_encode([
                        'revised_submission' => $submission->uid,
                        'title' => $submission->title,
                        'abstract' => $submission->abstract,
                        'keywords' => $submission->nicely_split_csv('keywords'),
                        'authors' => $revision_authors
                    ]), ENT_QUOTES, 'UTF-8');
                    $revision_button = '<a class="btn btn-outline-primary btn-sm" href="#" data-toggle="modal" data-target="#modal_revise" data-revision="'.$revision_details.'">Submit Revision</a>';
                }
                $organizer = '<em>not assigned</em>';
                if($submission->organizer) {
                    $organizer = '<a href="mailto:'.$submission->organizer->person->email.'">'.$submission->organizer->person->get_name_as_salutation().'</a>';
                }
                $status = $submission->get_status();
                if($submission->decision) {
                    $status = '<a href="#" data-toggle="modal" data-target="#modal_decisionletter" data-decision="'.
                        htmlspecialchars(json_encode([
                            'title' => $submission->decision,
                            'letter' => nl2br($submission->decision_letter)
                        ]), ENT_QUOTES, 'UTF-8').
                        '">'.$status.'</a>';
                    $table_archive .= '<tr>' .
                        '<td>' . $submission->get_authors(TRUE) . '</td>' .
                        '<td><a href="' . $link_submission . $submission->uid . '" title="' . $submission->title . '">' . (strlen($submission->title) <= 23 ? $submission->title : (trim(substr($submission->title, 0, 21)) . '...')) . '</a></td>' .
                        '<td>' . $organizer . '</td>' .
                        '<td>' . date($this->config['date_format'], $submission->decided) . '</td>' .
                        '<td>' . $status . '</td>' .
                        '</tr>';
                } else {
                    $table_submissions .= '<tr>' .
                        '<td>' . $submission->get_authors(TRUE) . '</td>' .
                        '<td><a href="' . $link_submission . $submission->uid . '" title="' . $submission->title . '">' . (strlen($submission->title) <= 23 ? $submission->title : (trim(substr($submission->title, 0, 21)) . '...')) . '</a></td>' .
                        '<td>' . date($this->config['date_format'], $submission->created) . $revision_badge . '</td>' .
                        '<td>' . $organizer . '</td>' .
                        '<td>' . $status . '</td>' .
                        '<td>' . $revision_button . '</td>' .
                        '</tr>';
                }
            }
            $salutation_options = '';
            foreach($this->config['salutation'] as $value => $display) {
                $salutation_options .= '<option value="'.$value.'">'.$display.'</option>';
            }
            $keywords_options = '';
            foreach($this->config['keywords'] as $value => $display) {
                $keywords_options .= '<label class="form-check-label d-block"><input type="checkbox" name="keywords[]" value="'.$value.'"> '.$display.'</label>';
            }
            $this->output('submissions_author', [
                'table_submissions' => $table_submissions,
                'table_archive' => $table_archive,
                'salutation_options' => $salutation_options,
                'keywords_options' => $keywords_options,
                'abstract_hidden' => $this->event->requires_abstract ? '' : 'd-none',
                'pdf_hidden' => $this->event->requires_pdf ? '' : 'd-none',
                'submissions_hidden' => $table_submissions == '' ? 'd-none' : '',
                'archive_hidden' => $table_archive == '' ? 'd-none' : '',

            ], 'submissions');
            return TRUE;
        }
        return FALSE;
    }

    protected function route_organizer_submissions() {
        if($this->require_login() && $this->is_current_user_an_organizer()) {
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                if (isset($_POST['reviewer']) && isset($_POST['submission'])) {
                    foreach ($_POST['reviewer'] as $reviewer_uid) {
                        $this->assign_review($_POST['submission'], $reviewer_uid);
                    }
                    $this->refresh();
                } elseif (isset($_POST['submissions']) && count($_POST['submissions']) > 0) {
                    foreach($_POST['submissions'] as $submission_uid => $reviewer_uid) {
                        $this->assign_review($submission_uid, $reviewer_uid);
                    }
                    $this->refresh();
                }
            }

            $table_reviewers = '';
            $potential_reviewers = '';
            foreach ($this->event->reviewers->entries as $reviewer) {
                $reviewer->person->inject_reviews();
                $total_reviews = 0;
                $done_reviews = 0;
                foreach($reviewer->person->reviews->entries as $review) {
                    if($review->status != 'cancelled') {
                        if ($review->submission->event->is_the_same_as($this->event)) {
                            $total_reviews++;
                            if ($review->suggestion != '') {
                                $done_reviews++;
                            }
                        }
                    }
                }
                $table_reviewers .= '<tr data-person="'.$reviewer->person->uid.'">'.
                    '<td><input type="checkbox" name="reviewer[]" value="'.$reviewer->uid.'"></td>'.
                    '<td>'.$reviewer->person->get_name_full().'</td>'.
                    '<td>'.implode(', ', $reviewer->person->get_keywords_as_array()).'</td>'.
                    '<td>'.$done_reviews.'/'.$total_reviews.' done</td>'.
                    '</tr>';

                $potential_reviewers .= '<option value="'.$reviewer->uid.'" data-person="'.$reviewer->person->uid.'" data-keywords="'.implode(',', $reviewer->person->get_keywords_as_array()).'" data-open-reviews="'.($total_reviews-$done_reviews).'">'.$reviewer->person->get_name_full().'</option>';
            }

            $table_submissions = '';
            $table_archive = '';
            $table_multipleassignment = '';
            $link_submission = $this->config['base_url'].$this->event->link.'/submission?id=';
            foreach ($this->event->submissions->entries as $submission) {
                $revisions = $this->event->submissions->get_entries_by_field('revised_submission', $submission->uid);
                if(count($revisions) > 0) {
                    continue;
                }
                $submission->inject_authors();
                $persons = [];
                foreach($submission->authors->entries as $author) {
                    $persons[] = $author->person->uid;
                }
                $submission->inject_reviews();
                $done_reviews = 0;
                $total_reviews = 0;
                foreach ($submission->reviews->entries as $review) {
                    if($review->status != 'cancelled') {
                        $total_reviews++;
                        if ($review->suggestion != '') {
                            $done_reviews++;
                        }
                    }
                }
                $review_cancellations = '';
                foreach($submission->reviews->entries as $review) {
                    if($review->status == 'assigned') {
                        $review_cancellations .= '<a class="dropdown-item" href="/'.$this->event->link.'/submission/cancel_review?id='.$review->uid.'">cancel review by ' . $review->reviewer->person->get_name_as_salutation() . '</a>';
                    }
                }
                if($review_cancellations != '') {
                    $review_cancellations .= '<div class="dropdown-divider"></div>';
                }
                $revision_badge = '';
                if($submission->is_revise) {
                    $revised_submission = $this->event->submissions->get_entry_by_uid($submission->revised_submission);
                    $revision_badge = ' <a href="'.$link_submission.$revised_submission->uid.'" class="badge badge-primary" title="This is a revision to a version submitted on '.date($this->config['date_format'], $revised_submission->created).'">R<small>&</small>R</a>';
                }
                if($submission->decision) {
                    $table_archive .= '<tr>' .
                        '<td>' . $submission->get_authors(TRUE) . '</td>' .
                        '<td><a href="' . $link_submission . $submission->uid . '" title="' . $submission->title . '">' . (strlen($submission->title) <= 23 ? $submission->title : (trim(substr($submission->title, 0, 21)) . '...')) . '</a></td>' .
                        '<td>' . ($submission->organizer ? $submission->organizer->person->get_name_as_salutation() : '<em>not assigned</em>') . '</td>' .
                        '<td>' . date($this->config['date_format'], $submission->decided) . $revision_badge . '</td>' .
                        '<td><a href="#" data-toggle="modal" data-target="#modal_decisionletter" data-decision="'.
                        htmlspecialchars(json_encode([
                            'title' => $submission->decision,
                            'letter' => nl2br($submission->decision_letter)
                        ]), ENT_QUOTES, 'UTF-8').
                        '">'.$submission->get_status().'</a></td>' .
                        '<td><a class="btn btn-outline-primary btn-sm" href="mailto:' . $submission->get_email_list() . '">email author(s)</a></td>' .
                        '</tr>';
                } else {
                    $table_submissions .= '<tr>' .
                        '<td>' . $submission->get_authors(TRUE) . '</td>' .
                        '<td><a href="' . $link_submission . $submission->uid . '" title="' . $submission->title . '">' . (strlen($submission->title) <= 23 ? $submission->title : (trim(substr($submission->title, 0, 21)) . '...')) . '</a></td>' .
                        '<td>' . date($this->config['date_format'], $submission->created) . $revision_badge . '</td>' .
                        '<td>' . ($submission->organizer ? $submission->organizer->person->get_name_as_salutation() : '<em>not assigned</em>') . '</td>' .
                        '<td>' . $submission->get_status() . '</td>' .
                        '<td>' . $done_reviews . '/' . $total_reviews . ' done</td>' .
                        '<td><div class="dropdown">' .
                        '<a class="btn btn-outline-primary btn-sm dropdown-toggle" href="#" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"></a>' .
                        '<div class="dropdown-menu dropdown-menu-right">' .
                        $review_cancellations .
                        '<a class="dropdown-item" href="#" data-toggle="modal" data-target="#modal_assignreview" data-submission="' . $submission->uid . '" data-keywords="' . implode(', ', $submission->get_keywords_as_array()) . '" data-persons="' . implode(',', $persons) . '">assign review</a>' .
                        '<a class="dropdown-item" href="/' . $this->event->link . '/submission/decide?id=' . $submission->uid . '">find decision</a>' .
                        '<a class="dropdown-item" href="mailto:' . $submission->get_email_list() . '">send email to author(s)</a>' .
                        '</div></div></td>' .
                        '</tr>';
                }

                $table_multipleassignment .= '<tr>'.
                    '<td>'.$submission->title.'</td>'.
                    '<td class="text-right"><select name="submissions['.$submission->uid.']" class="form-control" data-keywords="'.implode(',', $submission->get_keywords_as_array()).'" data-persons="'.implode(',', $persons).'">'.
                    '<option value="0">-- find a reviewer --</option>'.
                    $potential_reviewers.
                    '</select></td></tr>';
            }

            $multiassign_organizers = '';
            foreach ($this->event->organizers->entries as $organizer) {
                $multiassign_organizers .= '<a class="dropdown-item autoselect" href="#" data-order="'.$organizer->person->uid.'">'.$organizer->person->get_name_as_salutation().'</a>';
            }

            $this->output('submissions_organizer', [
                'table_submissions' => $table_submissions,
                'table_archive' => $table_archive,
                'table_reviewers' => $table_reviewers,
                'table_multipleassignment' => $table_multipleassignment,
                'multiassign_organizers' => $multiassign_organizers,
                'submissions_hidden' => $table_submissions == '' ? 'd-none' : '',
                'archive_hidden' => $table_archive == '' ? 'd-none' : ''
            ], 'submissions');
            return TRUE;
        }
        return FALSE;
    }

    protected function route_submission($submission_id) {
        if($this->require_login()) {
            $submission = $this->event->submissions->get_entry_by_uid($submission_id);
            $submission->inject_authors();
            $submission->inject_reviews();
            if($submission &&
                ($this->is_current_user_an_organizer() || $submission->contains_authoring_person($this->person) || $submission->contains_reviewing_person($this->person))) {
                $authors = '';
                $action_letter = '';
                if($this->is_current_user_an_organizer() || $submission->contains_authoring_person($this->person)) {
                    $authors = $submission->get_authors();
                    if($submission->is_revise) {
                        $action_letter = $submission->action_letter;
                    }
                }
                $revisions = $this->event->submissions->get_entries_by_field('revised_submission', $submission->uid);
                $this->output('submission', [
                    'link' => $this->config['base_url'].$this->event->link,
                    'event' => $this->event->title,
                    'date' => $authors == '' ? '' : date($this->config['date_format'], $submission->created),
                    'title' => $submission->title,
                    'authors' => $authors,
                    'authors_hidden' => $authors == '' ? 'd-none' : '',
                    'abstract' => nl2br($submission->abstract),
                    'abstract_hidden' => $this->event->requires_abstract ? '' : 'd-none',
                    'keywords' => implode(', ', $submission->get_keywords_as_array()),
                    'pdf' => $submission->pdf,
                    'pdfname' => str_replace($this->config['upload_dir'], '', $submission->pdf),
                    'pdf_hidden' => $this->event->requires_pdf ? '' : 'd-none',
                    'status' => $authors == '' ? '' : $submission->get_status(),
                    'revision_from' => $submission->revised_submission,
                    'revision_from_hidden' => $submission->revised_submission > 0 ? '' : 'd-none',
                    'revised_by' => count($revisions) > 0 ? $revisions[0]->uid : '',
                    'revised_by_hidden' => count($revisions) > 0 ? '' : 'd-none',
                    'action_letter' => $action_letter,
                    'action_letter_hidden' => $action_letter == '' ? 'd-none' : ''
                ]);
                return TRUE;
            }
        }
        return FALSE;
    }

    protected function route_profile() {
        if($this->require_login()) {
            $success = '';
            $message = '';
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                if(isset($_POST['firstname']) && isset($_POST['lastname']) && isset($_POST['salutation'])) {
                    $this->person->salutation = trim($_POST['salutation']);
                    $this->person->firstname = trim($_POST['firstname']);
                    $this->person->lastname = trim($_POST['lastname']);
                    if (isset($_POST['keywords'])) {
                        $this->person->keywords = implode(',', $_POST['keywords']);
                    } else {
                        $this->person->keywords = '';
                    }
                    if($this->person->update()) {
                        $success = 'Profile updated successfully.';
                    } else {
                        $message = 'An error occurred while saving. Please try again.';
                    }

                    //root mode is when the current user is the webmaster
                } elseif (isset($_POST['title']) && isset($_POST['link']) && $this->person->email == $this->config['mail']['from_email']) {
                    $link = urlencode(strtolower(trim($_POST['link'])));
                    $eventHandler = new ModelHandler($this->db, $this->config, 'event');
                    $eventHandler->collect_entries();
                    if(count($eventHandler->get_entries_by_field('link', $link)) > 0) {
                        $message = 'The event link "'.$link.'" is already taken. Please choose another one.';
                    } else {
                        $event = $eventHandler->add(new Event($this->db, $this->config, [
                            'title' => trim($_POST['title']),
                            'link' => $link
                        ]));
                        if ($event) {
                            if ($this->person->organizers->add(new Organizer($this->db, $this->config, [
                                'person' => $this->person,
                                'event' => $event
                            ]))) {
                                if($event->make_submitters_reviewers) {
                                    $event->inject_reviewers();
                                    $already_exists = FALSE;
                                    foreach($event->reviewers->entries as $reviewer) {
                                        if ($reviewer->person->uid == $this->person->uid &&
                                            $reviewer->event->uid == $event->uid) {

                                            $already_exists = TRUE;
                                            break;
                                        }
                                    }
                                    if(!$already_exists) {
                                        if ($event->reviewers->add(new Reviewer($this->db, $this->config, [
                                            'person' => $this->person,
                                            'event' => $event
                                        ]))) {
                                            $success = 'Event successfully created. You are now also organizer and reviewer of this event.';
                                        }
                                    }
                                    $message = 'Event successfully created but adding yourself as a reviewer resulted in an error.';
                                } else {
                                    $success = 'Event successfully created. You are now also organizer of this event.';
                                }
                            } else {
                                $message = 'Event successfully created but you could not be added as an organizer. Please inform the webmaster.';
                            }
                        } else {
                            $message = 'Event could not be created.';
                        }
                    }
                }
            }

            $salutation_options = '';
            foreach($this->config['salutation'] as $value => $display) {
                $salutation_options .= '<option value="'.$value.'" '.
                    ($this->person->salutation == $value ? 'selected' : '').
                    '>'.$display.'</option>';
            }
            $keywords_options = '';
            foreach($this->config['keywords'] as $value => $display) {
                $keywords_options .= '<label class="form-check-label d-block"><input type="checkbox" name="keywords[]" value="'.$value.'" '.
                    (in_array($value, explode(',', $this->person->keywords)) ? 'checked' : '').
                    '> '.$display.'</label>';
            }
            $events = '';
            $link_event = $this->config['base_url'].$this->event->link.'/event?id=';
            foreach($this->person->organizers->entries as $organizer) {
                $events .= '<tr><td>'.$organizer->event->title.'</td><td class="text-right">'.
                    '<a href="'.$link_event.$organizer->event->uid.'" class="btn btn-sm btn-outline-primary">edit</a>'.
                    '</td></tr>';
            }
            $this->output('profile', [
                'salutation_options' => $salutation_options,
                'keywords_options' => $keywords_options,
                'email' => $this->person->email,
                'firstname' => $this->person->firstname,
                'lastname' => $this->person->lastname,
                'events' => $events,
                //root mode is when the current user is the webmaster
                'events_hidden' => $events != '' ? '' : 'd-none',
                'eventcreate_hidden' => $this->person->email == $this->config['mail']['from_email'] ? '' : 'd-none',
                'success' => $success,
                'success_hidden' => $success == '' ? 'd-none' : '',
                'message' => $message,
                'message_hidden' => $message == '' ? 'd-none' : ''
            ]);
            return TRUE;
        }
        return FALSE;
    }

    protected function route_event($event_id) {
        if($this->require_login() && count($this->person->organizers->get_entries_by_field('event', $event_id)) == 1) {
            $success = '';
            $message = '';
            $event = $this->person->organizers->get_entries_by_field('event', $event_id);
            if(count($event) == 0) {
                return FALSE;
            } else {
                $event = $event[0]->event;
                $event->inject_organizers();
                $event->inject_reviewers();
            }

            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                if(isset($_POST['firstname']) && isset($_POST['lastname']) && isset($_POST['salutation'])) {
                    $person = $this->get_or_create_person(
                        $_POST['email'],
                        $_POST['salutation'],
                        $_POST['firstname'],
                        $_POST['lastname']
                    );
                    if($person) {
                        if($_POST['type'] == 'organizer') {
                            if ($event->organizers->add(new Organizer($this->db, $this->config, [
                                'person' => $person,
                                'event' => $event
                            ]))) {
                                if ($event->make_submitters_reviewers) {
                                    $event->inject_reviewers();
                                    $already_exists = FALSE;
                                    foreach($event->reviewers->entries as $reviewer) {
                                        if ($reviewer->person->uid == $person->uid &&
                                            $reviewer->event->uid == $event->uid) {

                                            $already_exists = TRUE;
                                            break;
                                        }
                                    }
                                    if(!$already_exists) {
                                        $event->reviewers->add(new Reviewer($this->db, $this->config, [
                                            'person' => $person,
                                            'event' => $event
                                        ]));
                                    }
                                }
                                $person->send_mail('Organizing privileges received for ' . $event->title,
                                    'you have just been named an organizer of the submission system for ' . $event->title . '.' . chr(10) . chr(10) .
                                    $this->config['base_url'] . $event->link . '/submissions' . chr(10));
                                $success = 'Person added successfully to list of organizers';
                            } else {
                                $message = 'Person could not be added as organizer. Please try again.';
                            }
                        } else {
                            $already_exists = FALSE;
                            foreach($event->reviewers->entries as $reviewer) {
                                if ($reviewer->person->uid == $person->uid &&
                                    $reviewer->event->uid == $event->uid) {

                                    $already_exists = TRUE;
                                    break;
                                }
                            }
                            if(!$already_exists) {
                                if ($event->reviewers->add(new Reviewer($this->db, $this->config, [
                                    'person' => $person,
                                    'event' => $event
                                ]))) {
                                    $success = 'Person added successfully to list of reviewers';
                                } else {
                                    $message = 'Person could not be added as reviewer. Please try again.';
                                }
                            } else {
                                $success = 'Person already added as reviewer.';
                            }
                        }
                    } else {
                        $message = 'Person could not be created. Please try again.';
                    }
                } elseif(isset($_POST['title']) && isset($_POST['description'])) {
                    $event->title = trim($_POST['title']);
                    $event->description = trim($_POST['description']);
                    $event->cfp_pdf = trim($_POST['cfp_pdf']);
                    $cfp_deadline = \DateTime::createFromFormat('Y-m-d\TH:i', $_POST['cfp_deadline']);
                    $event->cfp_deadline = $cfp_deadline ? $cfp_deadline->format('U') : NULL;
                    $review_deadline = \DateTime::createFromFormat('Y-m-d\TH:i', $_POST['review_deadline']);
                    $event->review_deadline = $review_deadline ? $review_deadline->format('U') : NULL;
                    if (isset($_POST['requires_abstract']) && $_POST['requires_abstract'] == 1) {
                        $event->requires_abstract = 1;
                    } else {
                        $event->requires_abstract = 0;
                    }
                    if (isset($_POST['requires_pdf']) && $_POST['requires_pdf'] == 1) {
                        $event->requires_pdf = 1;
                    } else {
                        $event->requires_pdf = 0;
                    }
                    if (isset($_POST['make_submitters_reviewers']) && $_POST['make_submitters_reviewers'] == 1) {
                        $event->make_submitters_reviewers = 1;
                    } else {
                        $event->make_submitters_reviewers = 0;
                    }
                    if($event->update()) {
                        $this->refresh();
                    } else {
                        $message = 'An error occurred while saving. Please try again.';
                    }
                }
            } elseif (isset($_GET['remove_organizer'])) {
                $organizer = $event->organizers->get_entry_by_uid(intval($_GET['remove_organizer']));
                if($organizer && !$organizer->person->is_the_same_as($this->person)) {
                    $organizer->remove();
                    $this->redirect('event?id='.$event_id);
                }
            } elseif (isset($_GET['remove_reviewer'])) {
                $reviewer = $event->reviewers->get_entry_by_uid(intval($_GET['remove_reviewer']));
                if($reviewer) {
                    $reviewer->remove();
                    $this->redirect('event?id='.$event_id);
                }
            }

            $salutation_options = '';
            foreach($this->config['salutation'] as $value => $display) {
                $salutation_options .= '<option value="'.$value.'">'.$display.'</option>';
            }
            $reviewers = '';
            foreach($event->reviewers->entries as $reviewer) {
                $reviewers .= '<tr><td>'.$reviewer->person->get_name_as_author().'</td><td class="text-right">'.
                    '<a href="'.$this->event->link.'/event?id='.$event_id.'&remove_reviewer='.$reviewer->uid.'" class="btn btn-sm btn-outline-primary">remove</a>'.
                    '</td></tr>';
            }
            $organizers = '';
            foreach($event->organizers->entries as $organizer) {
                $removal_link = '';
                if(!$organizer->person->is_the_same_as($this->person)) {
                    $removal_link = '<a href="'.$this->event->link.'/event?id='.$event_id.'&remove_organizer='.$organizer->uid.'" class="btn btn-sm btn-outline-primary">remove</a>';
                }
                $organizers .= '<tr><td>'.$organizer->person->get_name_as_author().'</td><td class="text-right">'.$removal_link.'</td></tr>';
            }
            $this->output('event', [
                'salutation_options' => $salutation_options,
                'link' => $this->config['base_url'].$event->link,
                'title' => $event->title,
                'description' => $event->description,
                'cfp_pdf' => $event->cfp_pdf,
                'cfp_deadline' => date('Y-m-d\TH:i', $event->cfp_deadline),
                'review_deadline' => date('Y-m-d\TH:i', $event->review_deadline),
                'requires_abstract_checked' => $event->requires_abstract ? 'checked' : '',
                'requires_pdf_checked' => $event->requires_pdf ? 'checked' : '',
                'make_submitters_reviewers_checked' => $event->make_submitters_reviewers ? 'checked' : '',
                'reviewers' => $reviewers,
                'organizers' => $organizers,
                'success' => $success,
                'success_hidden' => $success == '' ? 'd-none' : '',
                'message' => $message,
                'message_hidden' => $message == '' ? 'd-none' : ''
            ]);
            return TRUE;
        }
        return FALSE;
    }

    protected function route_cancel_review($review_id) {
        if($this->require_login() && $this->is_current_user_an_organizer()) {
            $reviewHandler = new ModelHandler($this->db, $this->config, 'review');
            $reviewHandler->sql_filter = 'uid = '.intval($review_id);
            $reviewHandler->collect_entries();
            $review = $reviewHandler->get_entry_by_uid($review_id);
            if ($review && $review->submission->event->is_the_same_as($this->event)) {
                $review->status = 'cancelled';
                $review->update();
                $review->reviewer->person->send_mail('Review cancelled for ' . $this->event->title,
                    'the review on the following submission has just been cancelled by an organizer. No need to get active at this point.' . chr(10) . chr(10) .
                    $review->submission->title . chr(10) . chr(10));
                $this->redirect('submissions');
                return TRUE;
            }
        }
        return FALSE;
    }

    protected function route_decide($submission_id) {
        if($this->require_login() && $this->is_current_user_an_organizer()) {
            $submission = $this->event->submissions->get_entry_by_uid($submission_id);
            if ($submission) {
                $submission->inject_authors();
                $submission->inject_reviews();

                if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                    if(isset($_POST['decision']) && $_POST['decision'] != '' && isset($this->config['decision'][$_POST['decision']]) &&
                        isset($_POST['decision_letter']) && $_POST['decision_letter'] != '') {

                        $submission->decided = time();
                        $submission->decision = $_POST['decision'];
                        $submission->decision_letter = trim($_POST['decision_letter']);
                        if($submission->update()) {
                            foreach ($submission->authors->entries as $author) {
                                $author->person->send_mail('Decision on your submission for ' . $this->event->title,
                                    $submission->decision_letter,
                                    FALSE
                                );
                            }
                            $this->redirect('submissions');
                        } else {
                            $this->output('error', ['error' => 'Uh, this decision could not be saved. Please try again. No e-mails have been sent.']);
                            return FALSE;
                        }
                    }
                }

                $authors = $submission->get_authors();
                $revisions = $this->event->submissions->get_entries_by_field('revised_submission', $submission->uid);
                $nav_reviews = '';
                $tab_reviews = '';
                for($i = 0; $i < $submission->reviews->count(); $i++) {
                    $review = $submission->reviews->get($i);
                    $nav_reviews .= '<li class="nav-item" role="presentation">'.
                        '<a class="nav-link" id="nav_review'.$i.'" data-toggle="tab" href="#tab_review'.$i.'" role="tab" aria-controls="tab_review'.$i.'" aria-selected="false">'.
                        'Review by '.$review->reviewer->person->get_name_full().
                        '</a></li>';
                    $tab_reviews .= '<div class="tab-pane fade" id="tab_review'.$i.'" role="tabpanel" aria-labelledby="nav_review'.$i.'"><dl>'.
                        '<dt>Reviewer</dt><dd>'.$review->reviewer->person->get_name_as_author().'</dd>'.
                        '<dt>Status</dt><dd>'.$review->status.'</dd>'.
                        '<dt>Review Date</dt><dd>'.date($this->config['date_format'], $review->reviewed).'</dd>'.
                        '<dt>Review Suggestion</dt><dd>'.$review->suggestion.'</dd>'.
                        '<dt>Confidential Comments</dt><dd>'.nl2br($review->text_to_organizers).'</dd>'.
                        '<dt>Comments to the Authors</dt><dd class="review_comment">'.nl2br($review->text_to_authors).'</dd>'.
                        '</dl></div>';
                }

                $decision_options = '';
                foreach($this->config['decision'] as $value => $display) {
                    $decision_options .= '<option value="'.$value.'">'.$display.'</option>';
                }

                $decision_salutation = 'Dear ';
                foreach($submission->authors->entries as $author) {
                    $decision_salutation .= $author->person->get_name_as_salutation().', ';
                }

                $this->output('decide', [
                    'link' => $this->config['base_url'].$this->event->link,
                    'event' => $this->event->title,
                    'date' => date($this->config['date_format'], $submission->created),
                    'title' => $submission->title,
                    'authors' => $authors,
                    'abstract' => nl2br($submission->abstract),
                    'abstract_hidden' => $this->event->requires_abstract ? '' : 'd-none',
                    'keywords' => implode(', ', $submission->get_keywords_as_array()),
                    'pdf' => $submission->pdf,
                    'pdfname' => str_replace($this->config['upload_dir'], '', $submission->pdf),
                    'pdf_hidden' => $this->event->requires_pdf ? '' : 'd-none',
                    'revision_from' => $submission->revised_submission,
                    'revision_from_hidden' => $submission->revised_submission > 0 ? '' : 'd-none',
                    'revised_by' => count($revisions) > 0 ? $revisions[0]->uid : '',
                    'revised_by_hidden' => count($revisions) > 0 ? '' : 'd-none',
                    'nav_reviews' => $nav_reviews,
                    'tab_reviews' => $tab_reviews,
                    'decision_options' => $decision_options,
                    'decision_salutation' => $decision_salutation,
                    'decision_greeting' => $this->person->get_name_as_salutation()
                ], 'submissions');
                return TRUE;
            }
        }
        return FALSE;
    }
}
