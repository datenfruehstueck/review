# Review

Conference-like submission system to organize double blind reviews for students.

## What does this system do?

It provides a manuscript review system with less options to be used in teaching. Essentially, you create an event (e.g., a seminar or an actual conference) and invite participants to upload/submit their PDF manuscript (could be extended abstracts, of course). Once submitted, you can assign reviewers and manage their reviews. 

## Why?

For students to learn how the double blind review system works. And to improve projects/papers.

## Can I use it?

Yes, a current installation is available under https://review.datenfruehstueck.de. You will be greated with an error page, though. Use the link called "Register as organizer" in the footer. Access is granted upon request.

## Can I install it for myself?

Also yes. Copy everything onto a PHP-capable server, create a MySQL database (using the database.sql file), create an "uploads" folder and make it writable, and edit the config.php file to your liking/settings. The configuration file should be rather self-explanatory. Crucially, however, you need access to a mail account with SMTP sending available (which is rather common, though). This is necessary to ensure that the plethora of emails the system sends get sent properly. Then, just hit your main URL and "Register as organizer" (in the footer).

## Very briefly, how does it work?

It is all based on so-called events (e.g., a seminar or an actual conference). The link resembles that (e.g., review.datenfruehstueck.de/my_seminar) and has to be provided at all times (otherwise, an error appears). In other words, if you newly register as an organizer, you immediately need to provide details for your first event from where you can then take it forward. 