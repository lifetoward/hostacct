<?php

class messageComposer extends Action
{
	protected $recipients, $newMsg, $msgParent;

	// valid types include: plaintext, richtext, jscript, numeric
	protected $contentdef = array(
		 'intro'=>array(
            'shortname'=>'Introductory text',
            'type'=>'richtext',
            'description'=>"You may wish to explain the purpose of this message, or how it will be used, etc. This text will appear below the heading and above the composition input box.",
            'default'=>null )
        ,'closing'=>array(
            'shortname'=>'Closing text',
			'type'=>'richtext',
			'description'=>"This content will be displayed below the message composition input box and before the previous message in the thread.",
			'default'=>null )
		,'sendconfirm'=>array(
			'shortname'=>'Confirmation',
			'type'=>'plaintext',
			'description'=>"If this text is specified, a confirmation dialog box will pop up with this text after they push 'Send'. The user will be able to Cancel or click OK and send the message.",
			'default'=>null )
		,'fixedsubject'=>array(
			'shortname'=>'Fixed subject',
			'type'=>'plaintext',
			'description'=>"If this text is specified, the subject line of the message will be fixed to this value and will not be visible or editable in the composer.",
			'default'=>null )
		);

	function __construct(
		Element $author,  	            	// Element of the person (or heir thereof) who's sending
		array $recipients = null,  	             	// Array of Elements of the persons to whom the message is addressed; note that recipients can also be derived from the msgParent
		Element $msgParent = null )  	// msgrcpt with message prior within the thread, ie. the one responded-to or forwarded or otherwise referenced
	{
		$this->newMsg = Element::create('messages:element:message', array('author'=>$author));
		$this->recipients = $recipients;
		if ($msgParent->_type == 'messages:element:msgrcpt') {
			$this->msgParent = $msgParent->message;
			if ($this->msgParent->author->_handle == $author->_handle && !count($this->recipients))
				// When continuing on a message which is from me, following up may imply using the recipient list that was used previously.
				// However, this is not always correct, as the follow-up may be in the context of just one recipient. Therefore, we take this "resend to all" approach only if no recipient list is passed in.
				// Otherwise we just use the recipient list we have.
				foreach (Element::collection(array('type'=>'messages:element:msgrcpt', 'where'=>"message = {$this->msgParent->_id}")) as $parentRcpt)
					$this->recipients[$parentRcpt->recipient->_handle] = $parentRcpt->recipient;
			else if ($this->msgParent->author->_handle != $author->_handle)
				// When continuing on a message which is from someone else, replying implies sending back to the author of the message.
				if ($this->msgParent->author)
					$this->recipients[$this->msgParent->author->_handle] = $this->msgParent->author;
			$this->newMsg->parent = $this->msgParent;
			$this->newMsg->subject = $this->msgParent->subject;
		}
	}

	public function render( $args = array() , $post = null , $content = null )
	{
		$content = array_merge(array(
			// You can place system default content for this interaction here.
			), (array)$content);
		capcel_load_lib('render');
		capcel_include_stylesheet('messages', 'messages');

		if ($this->newMsg->_stored)
			return Action::COMPLETE; // we're already happy

		if ($args['act'] == 'cancel')
			return Action::CANCEL; // stop composing without sending

		if ($args['act'] == 'submit') {

			if (mb_strlen($post['subject']) < 1)
				$problems['subject'] = '<span class="alert">A subject is required.</span><br/>';
			$this->newMsg->subject = $post['subject'];
			if (mb_strlen($post['content']) < 1)
				$problems['content'] = '<span class="alert">Please provide text for your message.</span><br/>';
			$this->newMsg->content = $post['content'];

			if (!count($problems)) try {
				$this->newMsg->store();
				if (!count($this->recipients)) // Handles "open letters" wherein we must have our one msgrcpt record to no-one
					$this->recipients[] = null;
				foreach ($this->recipients as $recipient) {
					$msgrcpt = Element::create('messages:element:msgrcpt', array('message'=>$this->newMsg->_id, 'recipient'=>$recipient->_handle, 'msgstat'=>'new'));
					$msgrcpt->store();
					$delivered++;
					if ($recipient instanceof Messagee) // sometimes there's no recipient but we're still just writing out a record for the "open letter"... open letters never notify.
						$sent += $recipient->message_notifier($this->newMsg);
				}
				$this->rendered = '<p class="alert">Your message was sent'. ($delivered != 1 ? " to $delivered recipients" : null) .".".
						($sent ? ($sent > 1 ? " Email notifications were sent to $sent of the recipients." : " The recipient was notified by email.") : null) ."</p>\n";
				return Action::SUCCEED;

			} catch (Exception $ex) {
				logError($ex);
				$this->rendered = '<p class="alert">We encountered an error attempting to store the information in the system. Your message was not delivered. If this problem persists, please call InnerActive Wellness to report it.</p>';
				return Action::FAIL;
			}
		}

		// Otherwise we're prompting for the full information
		capcel_include_jscript('input', 'base');
		capcel_include_jscript('helpers', '.');
		if (count($this->recipients) > 1) {
			foreach ($this->recipients as $recipient)
				$recipientsRendered[] = $recipient->_rendered;
			$recipientList = implode(' ,&nbsp; ', $recipientsRendered);
			$recipientsOut = <<<end
<p><b>To: </b>
<span id="tolistctrl" onclick="if(this.nextSibling.style.display=='none'){this.innerHTML='(Hide list)';this.nextSibling.style.display='inline';}else{this.innerHTML='(Show list)';this.nextSibling.style.display='none';}">
(Show list)</span><span style="display:none"><br/>$recipientList</span></p>
end;
		} else foreach ((array)$this->recipients as $recipient) // in other words, the single recipient is actually known
			$recipientsOut = "<p><b>To: </b>". 	$recipient->_rendered ."</p>";
		$formTarget = $this->target_with_args(array('act'=>'submit'), true);
		$cancelTarget = $this->target_with_args(array('act'=>'cancel'));
		if ($this->newMsg->parent) {
			$leadup = $this->msgParent->author->_handle == $this->newMsg->author->_handle ? "Following up your earlier message:" : "In response to ". htmlentities("{$this->msgParent->author}") ."'s message:";
			$replyToContent = "<p><i><b>$leadup</b></i></p><pre>". htmlentities($this->msgParent->content) ."</pre>";
		}
		$subjectRow = $this->fixedsubject ?
			'<input type="hidden" name="subject" id="subject_input" value="'. $this->fixedsubject .'"/>' :
			'<p><b>Subject</b><br/>'. $problems['subject'] .'<input type="text" size="50" name="subject" id="subject_input" value="'. htmlentities($this->newMsg->subject) .'"/></p>';
		if ($this->sendconfirm)
			$confirmation = "if(confirm('$this->sendconfirm'))";
		$GLOBALS['headscript'] .= <<<endscript
var okflags={};
function capcel_validated(c,n,ok){if(n)okflags[n]=ok;}
function conditionalsubmit(c){for(n in okflags)if(!okflags[n]){alert('Please provide valid entries for all required fields (marked with the blue arrow).');return;}c.disabled=true;c.form.submit();}
endscript;
		$this->rendered = <<<end
$this->intro
<div class="composer">
$recipientsOut
<form class="composer" method="post" action="$formTarget">
$subjectRow
<p><b>Message</b><br/>$problems[content]<textarea name="content" id="content_input" cols="50" rows="20">{$this->newMsg->content}</textarea></p>
<p><button type="button" onclick="{$confirmation}form.submit()">Send</button> <button type="button" onclick="location.href='$cancelTarget'">Cancel</button></p>
$replyToContent
$this->closing
</form>
</div>
end;
		return ($this->status = Action::PROCEED); // assignment intended

	}

}
?>
