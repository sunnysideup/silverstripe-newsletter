<?php

class NewsletterSendingTestTask extends BuildTask {

	/**
	 * names for test mailing lists
	 * @var Array
	 */
	private static $mailing_lists = array(
		"AUTO-CREATED-TEST-MAILING-LIST-1",
		"AUTO-CREATED-TEST-MAILING-LIST-2"
	);

	/**
	 * Email that can be used for sending the test e-mails
	 * The @ sign will be replaced by [NUMBER] @
	 * so jo@test.com will become:
	 * jo.1@test.com, jo.2@test.com, jo.3@test.com, jo.4@test.com, jo.5@test.com, etc...
	 * in the tests.
	 *
	 * Example entry: testonly@mydomain.co.nz
	 *
	 * @var String
	 */
	private static $email_base = "";

	/**
	 * Number of recipients to create
	 * @var Int
	 */
	private static $number_of_emails_to_test = 100;

	/**
	 *
	 * @var Array
	 */
	private static $newsletter_details = array(
		"Subject"            => "automated test for newsletter",
		"Content"            => "<h1>This is an automated test for the newsletter module.</h1>",
		"SendFrom"           => "",
		"ReplyTo"            => ""
	);

	protected $title = 'Test newsletter sending functionality';

	protected $description = 'Test the newsletter sending functionality by sending a large number of e-mails. You need a catch-all e-mail domain for this and this should be specified in the configs.';

	function run($request){
		//set to half-an-hour
		echo "<h1>************************ START OF NEWSLETTER TEST ****************************</h1>";

		echo "<h3>:::::::::::::::::::::::: SETTINGS ::::::::::::::::::::::::::::</h3>";
		DB::alteration_message("setting NewsletterSendController.items_to_batch_process: ".Config::inst()->get("NewsletterSendController", "items_to_batch_process").' - integer number of emails to send out in "batches" to avoid spin up costs');
		DB::alteration_message("setting NewsletterSendController.stuck_timeout: ".Config::inst()->get("NewsletterSendController", "stuck_timeout").' - integer minutes after which we consider an "InProgress" item in the queue "stuck"');
		DB::alteration_message("setting NewsletterSendController.retry_limit: ".Config::inst()->get("NewsletterSendController", "retry_limit").' - integer number of times to retry sending email that get "stuck"');
		DB::alteration_message("setting NewsletterSendController.throttle_batch_delay: ".Config::inst()->get("NewsletterSendController", "throttle_batch_delay").' - integer seconds to wait between sending out email batches.');
		echo "<h3>:::::::::::::::::::::::: END SETTINGS ::::::::::::::::::::::::::::</h3>";

		set_time_limit(600);
		if(isset($_GET["count"])) {
			Config::inst()->update("NewsletterSendingTestTask", "number_of_emails_to_test", intval($_GET["count"]));
		}
		if(isset($_GET["email"])) {
			Config::inst()->update("NewsletterSendingTestTask", "email_base", Convert::raw2sql($_GET["count"]));
		}
		DB::alteration_message(
			"<h3>This tasks helps you to send
			".$this->Config()->get("number_of_emails_to_test")." emails to ".
			str_replace('@', '[#]@', $this->Config()->get("email_base")).
			" (you can change these by settings by adding _GET variables <i>count</i> and <i>email</i> respectivly.)  using the newsletter module.</h3>"
		);
		if(isset($_GET["create"]) && $_GET["create"] == 1) {
			$this->addMailingLists();
			$this->addRecipients();
			$newsletter = $this->addNewsletter();
			echo "<h1>Newsletter has now been setup, please visit <a href=\"/admin/newsletter/Newsletter/EditForm/field/Newsletter/item/".$newsletter->ID."/edit\">open this newsletter now to test the sending.</a> ... </h1>";
		}
		elseif(isset($_GET["remove"]) && $_GET["remove"] == 1) {
			$this->deleteNewsletter();
			$this->deleteRecipients();
			$this->deleteMailingLists();
		}
		else{
			DB::alteration_message("NB: you will need to set a _GET variable create=1 to start this test, and remove=1 to delete this test.");
		}
		echo "<h1>************************ END OF NEWSLETTER TEST ****************************</h1>";
	}

	/**
	 *
	 * @return Array
	 */
	protected function addMailingLists(){
		$arrayOfMailingLists = array();
		$lists = $this->Config()->get("mailing_lists");
		foreach($lists as $list) {
			$mailingList = MailingList::get()->filter(array("Title" => $list))->first();
			if(!$mailingList) {
				DB::alteration_message("Creating mailing $list", "created");
				$mailingList = new MailingList();
				$mailingList->Title = $list;
				$mailingList->write();
			}
			else {
				DB::alteration_message("Mailing $list already exists.");
			}
			$arrayOfMailingLists[$mailingList->ID] = $mailingList->ID;
		}
		return $arrayOfMailingLists;
	}


	protected function addRecipients(){
		$mailingLists = $this->addMailingLists();
		$max = $this->Config()->get("number_of_emails_to_test");
		$base = $this->Config()->get("email_base");
		for($i = 0; $i < $max; $i++){
			$email = str_replace('@', ".".$i.'@', $base);
			$recipient = Recipient::get()->filter(array("Email" => $email))->first();
			if(!$recipient) {
				DB::alteration_message("Creating recipient with email $email", "created");
				$recipient = new Recipient();
				$recipient->Email = $email;
				$recipient->write();
			}
			else {
				DB::alteration_message("No need to create recipient with email $email");
			}
			$recipient->MailingLists()->addMany($mailingLists);
		}
	}

	/**
	 *
	 * @return Newsletter
	 */
	protected function addNewsletter(){
		$mailingLists = $this->addMailingLists();
		$newsletterArray = $this->Config()->get("newsletter_details");
		$newsletter = Newsletter::get()->filter($newsletterArray)->first();
		if(!$newsletter) {
			$newsletter = new Newsletter();
			foreach($newsletterArray as $field => $value){
				$newsletter->$field = $value;
			}
			$newsletter->write();
			DB::alteration_message("Created newsletter with the following characteristics: ".implode(", ",$newsletterArray)."." , "created");
		}
		else{
			DB::alteration_message("A newsletter with the following characteristics already exists: ".implode(", ",$newsletterArray)."." );
		}
		$newsletter->MailingLists()->addMany($mailingLists);
		return $newsletter;
	}

	protected function deleteNewsletter(){
		$mailingLists = $this->addMailingLists();
		$newsletterArray = $this->Config()->get("newsletter_details");
		$newsletter = Newsletter::get()->filter($newsletterArray)->first();
		if($newsletter) {
			DB::alteration_message("deleting newsletter with the following characteristics: Subject = ".$newsletterArray["Subject"]."." , "deleted");
			$newsletter->MailingLists()->removeMany($mailingLists);
			$newsletter = new Newsletter();
		}
	}

	protected function deleteRecipients(){
		$mailingLists = $this->addMailingLists();
		$max = $this->Config()->get("number_of_emails_to_test");
		$base = $this->Config()->get("email_base");
		for($i = 0; $i < $max; $i++){
			$email = str_replace('@', ".".$i.'@', $base);
			$recipient = Recipient::get()->filter(array("Email" => $email))->first();
			if($recipient) {
				DB::alteration_message("Deleting recipient with email $email", "deleted");
				$recipient->MailingLists()->removeMany($mailingLists);
				$recipient->delete();
			}
			else {
				DB::alteration_message("No need to delete recipient with email $email");
			}
		}
	}

	protected function deleteMailingLists(){
		$lists = $this->Config()->get("mailing_lists");
		foreach($lists as $list) {
			$mailingList = MailingList::get()->filter(array("Title" => $list))->first();
			if($mailingList) {
				DB::alteration_message("Deleting mailing $list", "deleted");
				$mailingList->delete();
			}
			else {
				DB::alteration_message("No need to delete mailing list $list");
			}
		}
	}

}
