<?php

namespace ReviewOrganizer;

use PHPMailer\PHPMailer\PHPMailer;

require_once('src/PHPMailer/src/PHPMailer.php');
require_once('src/PHPMailer/src/Exception.php');
require_once('src/PHPMailer/src/SMTP.php');

class Person extends AbstractModel {
	protected $table = 'person';
	public $email;
	public $password_hash;
	public $salutation;
	public $firstname;
	public $lastname;
	public $keywords;
	public $reviews;
	public $submissions;
	public $organizers;
	protected $mailer = NULL;

    function __construct(&$db, &$config, $row) {
        $this->mailer = new PHPMailer(FALSE);
        $this->mailer->isSMTP();
        $this->mailer->Host = $config['mail']['host'];
        $this->mailer->SMTPAuth = TRUE;
        if ($config['mail']['tls']) {
            $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        }
        $this->mailer->Username = $config['mail']['user'];
        $this->mailer->Password = $config['mail']['password'];
        $this->mailer->Port = $config['mail']['port'];
        $this->mailer->CharSet = PHPMailer::CHARSET_UTF8;
        $this->mailer->setFrom($config['mail']['from_email'], $config['mail']['from_name']);
        $this->mailer->addReplyTo($config['mail']['from_email'], $config['mail']['from_name']);
        $this->mailer->isHTML(FALSE);

        parent::__construct($db, $config, $row);
    }

	function hash_password($password) {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    function generate_password() {
        $password_bag = '1234567890ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz!"$%&/()=?+*#.:-_,;<>';
        $password = substr(str_shuffle($password_bag), 0, 3).
            substr(str_shuffle($password_bag), 0, 3).
            substr(str_shuffle($password_bag), 0, 3).
            substr(str_shuffle($password_bag), 0, 3);
        $this->password_hash = $this->hash_password($password);
        return $password;
    }

	function check_password($password) {
		return password_verify($password, $this->password_hash);
	}

	function get_name_as_author($shortened = FALSE) {
	    return $this->lastname.($shortened ? '' : (', '.$this->firstname));
    }

    function get_name_as_salutation() {
	    return $this->salutation.' '.$this->lastname;
    }

    function get_name_full() {
	    return $this->firstname.' '.$this->lastname;
    }
	
	function send_mail($subject, $message, $add_salutation = TRUE) {
        $this->mailer->clearAllRecipients();
        $this->mailer->addAddress($this->email);
        //$subject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $this->mailer->Subject = $subject;
        if($add_salutation) {
            $message = 'Dear '.$this->get_name_as_salutation().','.chr(10).chr(10).$message;
        }
        $message .= chr(10).chr(10).'--'.chr(10).'This message was automatically created and sent from '.$this->config['base_url'].chr(10);
        $this->mailer->Body = $message;
        $this->mailer->send();
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

	function inject_reviews() {
	    $this->reviews = new ModelHandler($this->db, $this->config, 'review');
	    $this->reviews->sql_filter = '`reviewer` IN (SELECT `uid` FROM `reviewer` WHERE `person` = '.intval($this->uid).')';
	    $this->reviews->collect_entries();
    }

	function inject_submissions() {
	    $this->submissions = new ModelHandler($this->db, $this->config, 'submission');
	    $this->submissions->sql_filter = '`uid` IN (SELECT `submission` FROM `author` WHERE `person` = '.intval($this->uid).')';
	    $this->submissions->collect_entries();
    }

	function inject_organizers() {
	    $this->organizers = new ModelHandler($this->db, $this->config, 'organizer');
	    $this->organizers->sql_filter = '`person` = '.intval($this->uid);
	    $this->organizers->collect_entries();
    }
}
