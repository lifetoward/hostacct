<?php

class messageDisplay extends Action
{
	private $msg;

	function __get( $name )
	{
		if ($name == 'author')
			return "{$this->msg->message->author}";
		return parent::__get($name);
	}

	// Display the message and allow it to be "unread", forwarded, or replied-to.
	function __construct( Element $msgrcpt, Element $perspective = null ) // the perspective is what allows us to determine whether the message can be marked read or unread.
	{
		if ($msgrcpt->_type != 'messages:element:msgrcpt')
			throw new Exception("messageDisplay requires a msgrcpt object");
		$this->msg = $msgrcpt;
		if ($this->msg->msgstat == 'new' &&
				( ( !$this->msg->recipient && $perspective->_handle != $msgrcpt->message->author->_handle) || // an open letter gets marked read whenever anyone except the author reads it
					$perspective->_handle == $this->msg->recipient->_handle ) ) { // when addressed to me, I mark it read
			$this->msg->acceptValues(array('msgstat'=>'read'));
			$this->msg->store();
		}
	}

	public function render( Context $c )
	{
		$c->linkStylesheet('messages/messages');
		list($date, $time) = explode(" ", $this->msg->message->composed, 2);
		$datetime = t_timeofday::format($time) ." on ". t_date::format($date, 'W m d');
		if (!$author = $this->msg->message->»author)
			$author = "InnerActive Wellness";
		// need the parent message, if applicable
		$this->rendered = <<<end
$content[intro]
<div class="msgview">
<table id="header">
<tr><td class="author">$author:</td><td class="datetime">$datetime</td></tr>
</table><table id="header">
<tr><td class="subject">{$this->msg->message->subject}</td><td class="status">{$this->msg->»msgstat}</td></tr>
</table>
<div class="msgcontent">
{$this->msg->message->content}

</div></div>
end;
		return static::COMPLETE;
	}

}
