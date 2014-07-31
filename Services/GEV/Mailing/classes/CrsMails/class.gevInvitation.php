<?php

require_once("Services/GEV/Mailing/classes/class.gevCrsAutoMail.php");
require_once("Services/GEV/Mailing/classes/class.gevCrsAdditionalMailSettings.php");

class gevInvitation extends gevCrsAutoMail {
	protected $mail_settings;
	protected $attachements;
	
	const DAYS_BEFORE_COURSE_START = 14;
	
	const STOP_SEND_EXCEPTION_MESSAGE = "Stop sending invitation mail. No invitation mail defined.";

	public function __construct($a_crs_id, $a_id) {
		parent::__construct($a_crs_id, $a_id);

		$this->mail_settings = null;
		$this->attachments = null;
		
		
		$additional_mail_settings = new gevCrsAdditionalMailSettings($a_crs_id);
		$this->days_before_course_start = $additional_mail_settings->getInvitationMailingDate();
	}

	public function getTitle() {
		return "Einladung Teilnehmer";
	}

	public function getDescription() {
		return $this->days_before_course_start." Tage vor Trainingsbeginn";
	}
	
	public function _getDescription() {
		return "";
	}
	
	public function getTemplateCategory() {
		return "";
	}

	public function getScheduledFor() {
		$date = $this->getCourseUtils()->getStartDate();
		if ($date) {
			$date->increment(IL_CAL_DAY, -1 * $this->days_before_course_start);
		}
		return $date;
	}

	public function getUsersOnly() {
		return true;
	}

	public function getRecipientUserIDs() {
		return array_merge($this->getCourseParticipants(), $this->getCourseSpecialMembers());
	}

	public function getRecipientAddresses() {
		$ret = array();
		foreach ($this->getRecipientUserIDs() as $user_id) {
			$ret[] = ilObjUser::_lookupEmail($user_id);
		}
		return $ret;
	}

	public function getCC($a_recipient) {
		return array();
	}

	public function getMail($a_recipient) {
		if (!$this->checkUserID($a_recipient)) {
			throw new Exception("GEV-Invitation-Mails will only work for ILIAS-Users.");
		}

		$function = $this->getUserFunction($a_recipient);

		// function will be null if user is not member of the course. Fall back to
		// standard mail.
		if ($function === null) {
			$function = "standard";
		}

		return $this->getMailFor($function, $a_recipient);
	}

	public function getMailFor($a_function, $a_recipient) {
		require_once("./Services/GEV/Mailing/classes/class.gevCrsInvitationMailSettings.php");
		
		if ($this->mail_settings === null) {
			$this->mail_settings = new gevCrsInvitationMailSettings($this->crs_id);
		}

		$template_id = $this->mail_settings->getTemplateFor($a_function);

		if($template_id == -1) {
			$template_id = $this->mail_settings->getTemplateFor("standard");
			$attachments = $this->mail_settings->getAttachmentsFor("standard");
		}
		else {
			$attachments = $this->mail_settings->getAttachmentsFor($a_function);
		}

		// Just stop sending if theres no mail defined.
		if ($template_id == -1) {
			return null;
		}
		

		$message = $this->getMessageFromTemplate($template_id
												, $a_recipient
												, $this->getFullnameForTemplate($a_recipient)
												, $this->getEmailForTemplate($a_recipient));

		return array( "from" => $this->getFrom()
					, "to" => $this->getTo($a_recipient)
					, "cc" => $this->getCC($a_recipient)
					, "bcc" => $this->getBCC($a_recipient)
					, "subject" => $message["subject"]
					, "message_plain" => $message["plain"]
					, "message_html" => $message["html"]
					, "attachments" => $attachments
					, "frame_plain" => $this->template_frame->getPlainTextFrame()
					, "frame_html" => $this->template_frame->getHtmlFrame()
					, "image_path" => $this->template_frame->getFileSystemBasePath()."/"
									  .$this->template_frame->getImageName()
					, "image_styles" => $this->template_frame->getImageStyles()
					);
	}

	public function getAttachmentPath($a_name) {
		if (!$this->getAttachments()->isAttachment($a_name)) {
			throw new Exception("'".$a_name."' is no attachment of participant invitations.");
		}

		return $this->getAttachments()->getPathTo($a_name);
	}
}

?>