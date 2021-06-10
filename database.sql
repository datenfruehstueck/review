SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;


CREATE TABLE `author` (
  `uid` int(11) NOT NULL,
  `submission` int(11) DEFAULT NULL,
  `person` int(11) DEFAULT NULL,
  `position` int(3) NOT NULL DEFAULT '1'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `event` (
  `uid` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text,
  `cfp_pdf` varchar(255) DEFAULT NULL,
  `cfp_deadline` int(35) DEFAULT NULL,
  `review_deadline` int(35) DEFAULT NULL,
  `link` varchar(100) NOT NULL,
  `requires_abstract` tinyint(2) DEFAULT '1',
  `requires_pdf` tinyint(2) DEFAULT '1',
  `make_submitters_reviewers` tinyint(2) DEFAULT '1'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `organizer` (
  `uid` int(11) NOT NULL,
  `event` int(11) NOT NULL,
  `person` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `person` (
  `uid` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) DEFAULT NULL,
  `salutation` varchar(50) NOT NULL,
  `firstname` varchar(255) DEFAULT NULL,
  `lastname` varchar(255) DEFAULT NULL,
  `keywords` varchar(255) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `review` (
  `uid` int(11) NOT NULL,
  `reviewer` int(11) NOT NULL,
  `submission` int(11) NOT NULL,
  `status` varchar(100) NOT NULL,
  `reviewed` int(35) DEFAULT NULL,
  `suggestion` varchar(100) DEFAULT NULL,
  `text_to_authors` text,
  `text_to_organizers` text
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `reviewer` (
  `uid` int(11) NOT NULL,
  `event` int(11) NOT NULL,
  `person` int(11) NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

CREATE TABLE `submission` (
  `uid` int(11) NOT NULL,
  `event` int(11) NOT NULL,
  `created` int(35) NOT NULL,
  `organizer` int(11) DEFAULT NULL,
  `title` text,
  `abstract` text,
  `keywords` varchar(255) DEFAULT NULL,
  `pdf` varchar(255) DEFAULT NULL,
  `is_revise` tinyint(2) DEFAULT '0',
  `revised_submission` int(11) DEFAULT NULL,
  `action_letter` text,
  `decided` int(35) DEFAULT NULL,
  `decision` varchar(255) DEFAULT NULL,
  `decision_letter` text
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


ALTER TABLE `author`
  ADD PRIMARY KEY (`uid`);

ALTER TABLE `event`
  ADD PRIMARY KEY (`uid`),
  ADD UNIQUE KEY `event_link_uindex` (`link`);

ALTER TABLE `organizer`
  ADD PRIMARY KEY (`uid`);

ALTER TABLE `person`
  ADD PRIMARY KEY (`uid`),
  ADD UNIQUE KEY `person_email_uindex` (`email`);

ALTER TABLE `review`
  ADD PRIMARY KEY (`uid`);

ALTER TABLE `reviewer`
  ADD PRIMARY KEY (`uid`);

ALTER TABLE `submission`
  ADD PRIMARY KEY (`uid`);


ALTER TABLE `author`
  MODIFY `uid` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `event`
  MODIFY `uid` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `organizer`
  MODIFY `uid` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `person`
  MODIFY `uid` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `review`
  MODIFY `uid` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `reviewer`
  MODIFY `uid` int(11) NOT NULL AUTO_INCREMENT;

ALTER TABLE `submission`
  MODIFY `uid` int(11) NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
