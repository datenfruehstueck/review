<?php

$config = [
    'base_url' => 'https://review.datenfruehstueck.de/',
    'upload_dir' => 'uploads/',
    'date_format' => 'Y-m-d H:i',

	'database' => [
        'host' => 'localhost',
        'user' => 'db.user',
        'password' => 'db.password',
        'name' => 'db.name'
	],

    'mail' => [
        'from_name' => 'Your Name',
        'from_email' => 'Your Mail',
        'host' => 'Your SMTP Mail Server Host',
        'port' => 465,
        'tls' => TRUE,
        'user' => 'smtp login user',
        'password' => 'smtp login password'
    ],
	
	'decision' => [
		'Accept' => 'Accept this submission as is',
		'Minor Revision' => 'Accept this submission conditional on some minor changes',
		'Major Revise and Resubmit' => 'Invite this submission for resubmission and another round of review as it looks promising but requires several changes',
		'Reject' => 'Reject this submission as it is not suitable for further consideration'
	],

	'salutation' => [
		'Mrs' => 'Mrs',
		'Mr' => 'Mr',
		'Dr' => 'Dr',
		'Prof' => 'Prof'
	],

	'keywords' => [
        'children' => 'Children, Adolescents and Media',
        'cat' => 'Communication and Technology',
        'commhistory' => 'Communication History',
        'law' => 'Communication Law and Policy',
        'compmethods' => 'Computational Methods',
        'envircomm' => 'Environmental Communication',
        'ethnicity' => 'Ethnicity and Race in Communication',
        'feminism' => 'Feminist Scholarship',
        'games' => 'Game Studies',
        'globalcomm' => 'Global Communication and Social Change',
        'healthcomm' => 'Health Communication',
        'infosys' => 'Information Systems',
        'devcomm' => 'Instructional and Developmental Communication',
        'intercultural' => 'Intercultural Communication',
        'interpersonal' => 'Interpersonal Communication',
        'journalism' => 'Journalism Studies',
        'language' => 'Language and Social Interaction',
        'masscomm' => 'Mass Communication',
        'orgacomm' => 'Organizational Communication',
        'critique' => 'Philosophy, Theory and Critique',
        'polcomm' => 'Political Communication',
        'popcomm' => 'Popular Communication',
        'pr' => 'Public Relations',
        'visual' => 'Visual Communication Studies'
	],
];
