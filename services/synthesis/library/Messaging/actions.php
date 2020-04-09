<?php

function messages_dview( $reffocus, $refdata, $dview )
{
	capcel_load_lib('render');
	capcel_include_stylesheet('messages','messages');
	$mainTriggers = capcel_trigger('messages:action:compose', array('render'=>'capcel_button_trigger', 'arg*'=>array('focus'=>$reffocus['*fqn'], 'id'=>$refdata['_id'], 'exchange'=>$dview['exchange'])));
	$referent = Element::get($reffocus['*fqn'], $refdata['_id']);
	if (!$count = count($rcpts =
			Element::collection(array('type'=>'messages:element:msgrcpt', 'where'=>"{}_message.author = '$referent->_handle' OR {}.recipient = '$referent->_handle'", 'sortfield'=>'message_composed'))))
		$renderedRecords = '<p style="text-align:center">No messages.</p>';
	else {
		$ac = array('render'=>'capcel_method_trigger', 'dynarg'=>'id', 'arg*'=>array('focus'=>'messages:element:msgrcpt'));
		foreach ($rcpts AS $rcpt) {
			$rowClass = "alt". (++$alt)%2;
			$viewTrigger = capcel_trigger('messages:action:display', $ac, $rcpt->_id);
			list($date, $time) = explode(" ", $rcpt->message->composed, 2);
			$datetime = htmlentities(t_date::format($date, "w d m 'y")." ".t_timeofday::format($time));
			$rows .= "<tr class=\"$rowClass". ($rcpt->msgstat == 'new' ? ' unread' : null) ."\" onhover=\"backgroundColor='#CCCCFF'\" onclick=\"$viewTrigger\">".
				"<td>{$rcpt->message->author}</td><td>$datetime</td><td>{$rcpt->message->»subject}</td><td>$rcpt->»msgstat</td></tr>\n";
		}
		$renderedRecords = "<table class=\"browse\"><thead><tr><th>Sender</th><th>Composed</th><th>Subject</th><th>Status</th></tr></thead><tbody>$rows</tbody></table>\n";
	}
	return <<<end
<div class="inview"><form action="placeholder" method="post">
<span class="actions">$mainTriggers</span>
<h3>$dview[label] ($count)</h3>
$renderedRecords
</form></div>
end;
}

function composeMessage( &$frame )
{
	$args = $frame['arg*'];
	if (!$frame['composer']) {
		// As a library function, it's a small stretch to use employee as the presumed type of the authenticated instance, but it's passable for now.
		if (!$author = e_employee::get("login = '{$_SESSION['user']['user']}'"))
			if (!$author = e_person::get("login = '{$_SESSION['user']['user']}'"))
				return null;
		$frame['composer'] = new messageComposer(
				$author, // author
				$args['parent'] ? null : Element::collection($args), // recipients
				$args['parent'] /* parent message (which comes in object form now!) */ );
		$frame['composer']->argStyle = Context::TARGETS_EXPECTED;
	}
	switch ($frame['composer']->render( $args, $frame['post*'] )) {
		case Action::FAIL:
		case Action::PROCEED:
		case Action::SUCCEED:
			$back = capcel_exit_action();
			return "<span style=\"float:right\">$back</span>\n<h1>Compose your message</h1>\n<div style=\"padding:0 30%\">{$frame['composer']->rendered}</div>";
		case Action::COMPLETE:
		case Action::CANCEL:
			return null;
	}
}

function displayMessage( &$frame )
{
	$frame['msgrcpt'] = Element::get('messages:element:msgrcpt', $frame['arg*']['id']); // this should be a msgrcpt selected from a message in the displayview
	$frame['perspective'] = e_employee::get("login = '{$_SESSION['user']['user']}'"); // this is the autenticated user as an employee
	if (!$frame['display'])
		$frame['display'] = new messageDisplay($frame['msgrcpt'], $frame['perspective']);
	$composeArgs = array('parent'=>$frame['msgrcpt']);
	if ($frame['perspective']->_handle == $frame['msgrcpt']->message->author->_handle) {
		$heading = "Message for {$frame['msgrcpt']->recipient}";
		$triggerLabel = 'Follow up';
		list($composeArgs['focus'], $composeArgs['id']) = explode('=', $frame['msgrcpt']->recipient->_handle, 2);
		// the convention for internal portals is that we view messages in the context of one recipient, and therefore would follow up only to them
	} else {
		$heading = "Message from {$frame['display']->author}";
		$triggerLabel = 'Reply';
		// The composer knows how to decide the reply recipient based on the parent msgrcpt
	}
	$extend = '<button id="extendTrigger" onclick="'. capcel_trigger('messages:action:compose', array('render'=>'capcel_method_trigger', 'arg*'=>$composeArgs)) ."\">$triggerLabel</button>";
	$back = capcel_exit_action();
	switch ($frame['display']->render(Context::create(Context::TARGETS_EXPECTED, array('_action'=>'displayMessage')))) {
		case Action::PROCEED:
		case Action::SUCCEED:
		case Action::COMPLETE:
			return "<span style=\"float:right\">$extend &nbsp; $back</span>\n<h1>$heading</h1>\n<div style=\"padding:0 20%\">{$frame['display']->rendered}</div>";
		case Action::FAIL:
		case Action::CANCEL:
			return null;
	}
}
