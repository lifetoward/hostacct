<?php
/**
* This is the generic library version of a frame template suitable for use with many interactive sessions.
* Extend this class to make a custom top-level class for direct invocation by a web-addressable handler for browser interaction.
* It features a prominent responsive banner containing the application / company name and an arbitrary number of dimensions of primary navigation based on a top-level set of "tabs".
* Each "tab" has its own persistent renderable instance, so a user can switch between tabs while each tab remembers its rendered state. Note that triggered submenus under a tab reset the rendered state for the whole tab.
* A tab remains selected until another tab is chosen and only one is renderable (active) at a time.
*
* The main view area is large and optimized for computer or tablet use, but should be suitable for use on small devices which can swap between landscape and portrait mode.
*
* To configure your subclass you need to set 3 static class variables:
*	- $reqrole - The name of the role required to use the portal frame. If left null, access is open to anyone who has access to the session type.
* 	- $navigators - A multi-level array which describes the menu hierarchy and action functions. You can provide it as a JSON string or a PHP array. (Format details below.)
*	- $home - A string which selects the menu option which should be active initially and when triggered by a user clicking the logo-title. It's in the format "0_1_2_1" where each number is an index into the menu options for a level.
*		So, in the example given, clicking the home/logo would mean "Select the first tab's second menu option's third menu option's second menu option." (Like all list arrays, they are 0-based-indexed.)
*
* Menu descriptions:
* Menus are described in a hierarchical tree of list-style arrays (arrays with sequential numeric keys), which means the meaning of the entries is POSITIONAL.
*	This choice was made to keep the specification short and quick to compose without a lot of redundant use of string keys, which in any language gets old fast.
* Thus the outermost structure is a list array, and the elements it contains are list arrays which represent the "nodes" of the top-level tabs.
* A node's elements are positionally defined as follows:
* 	[ type, label, reqrole, type-specific-information ]
* The only element which is required for ALL nodes is the type. The other elements are required depending on the type of node.
* The label is required for any node except a divider. It should be provided HTML-ready and may possibly contain markup.
* The reqrole is the name of an authorization role which is required to access what this node provides. Thus you can prevent access to whole menus or just actions depending on the user's role.
* The recognized types are:
*	- "divider" - Places a faint, disabled, horizontal-line divider within a list of menu options. No other elements of the node are required or used.
*	- "action" - Identifies a subclass of Action which can be rendered to produce the desired content. The type-specific information for an action is another list-array with these positional parameters:
*		- Action class name - example "a_Browse"
*		- Keyed array of action args - JSON example: { "class":"Client" }
*	- "menu" - Provides a submenu of additional nodes. The type-specific information is a list-array of nodes as defined here.
*	- (We intend to provide one or two other renderable types in the future, like "content", or "extlink" for example, in which case you can just render a file or launch a link in a new browser window, etc.
*
* So once you lay out what each of your menu options
*
* All original code.
* @package Synthesis
* @author Guy Johnson <Guy@SyntheticWebApps.com>
* @copyright 2014 Lifetoward LLC
* @license proprietary
*/
class FrameTemplate extends Frame
{
	/**
	* Subclasses should override the following values to create a meaningful frame with menu-based navigation.
	* @var array|string $navigators A deep array or its JSON representation defining the navigational aspects of the frame.
	* @var string $reqrole The authorization role required to render this page.
	* @var array $home The selector
	*/
	protected static $navigators, $reqrole, $home = '0';
	protected $navigation, // native format of the menuing info
		$selections= [], // array of lists of numbers, one list per top-level, indicating the navigational sequence through the menus which would arrive at the currently selected item
		$renderables = [], // array of [ action | content ] with one entry for each of the top-level navigation options (tabs) storing its state
		$active = 0; // Keeps track of which of the top-level menus is currently active. The selector can change this.
	use SerializationHelper;
	private static $savedProperties = ['selections', 'renderables', 'active'];

	public function render( Context $c )
	{
		// We need to unpack the navigators to make them ready to use within this instance.
		// Subclasses may wish to modify the contents of $navigators after this is done, although
		// they could just as well manage this through initializing their own $navigators property.
		if (!is_array(static::$navigators))
			$this->navigation = json_decode(static::$navigators, true);
		else
			$this->navigation = static::$navigators;

		$R = new HTMLRendering($c);
		$min = $_ENV['PHASE'] == 'dev' ? null : '.min';

		// Use JQuery UI
		$R->linkScript("$c->weblib/jquery/ui$min.js");
		$R->linkStylesheet("$c->weblib/jquery/ui$min.css");

		// Use Bootstrap CSS only
		$R->linkScript("$c->weblib/bootstrap-3/js/bootstrap$min.js");
		$R->linkStylesheet("$c->weblib/bootstrap-3/css/bootstrap$min.css");

		if (static::$reqrole && !$c->isAuthorized(static::$reqrole))
			$c->abort("You don't have permission (". static::$reqrole .") to access the ". get_called_class() ." features.");

		$args = $c->request['args'];
		if (isset($args['_']) && is_array($this->navigation[$args['_'] * 1]))
			list($topSel, $selection) = explode('_', $args['_'], 2);
			if (is_numeric($topSel) && is_array($this->navigation[$topSel])) { // we have a usable menu selection at the top at least
				$this->active = ($topSel *= 1);
				if ($selection != $this->selections[$topSel] || !$this->renderables[$topSel])
					$this->selections[$topSel] = $this->set_renderable($c, $this->navigation[$topSel], $args['_'], $topSel);
			}

		if (!count($this->selections)) // brand new interface... set navigation to the initializing selection
			$this->selections[$this::$home*1] = $this->set_renderable($c, $this->navigation[$this::$home*1], $this::$home, $this::$home*1);
		$apptitle = htmlentities($_ENV['ACCOUNT_NAME']);
		$homeTarget = htmlentities($c->target(null, false, '_', static::$home));
		$R->addStyles(self::get_styles(), "Base/FrameTemplate");
		$R->addReadyScript('$("body").css("padding-top",$("nav").height());', 'FrameTemplate navbar allowance');
		$menus = !count($this->navigation) ? null : "	<ul class=\"nav navbar-nav nav-tabs\">\n".
				$this->render_menuoptions($c, $this->navigation, $this->selections, array()) ."	</ul>\n".
				"	<ul class=\"nav navbar-nav navbar-right\"><li><a tabindex=\"". (count($this->navigation)+10001) ."\" href=\"$_SERVER[script]?_=logout\">Log out</a></li></ul>\n";
		$content = '<div class="container"><h1>Synthesis</h1><p>Please select an Action from the menus above.</p></div>'; // this is a default
		$renderable = $this->renderables[$this->active];

		if ($renderable instanceof Action) {
			$actionClass = get_class($renderable);
			try { // render the Action and capture its results
				$result = $renderable->render($c);
				if ($result instanceof HTMLRendering) {
					$content = $result->content;
					$R->incorporate($result, false);
				} else
					$R->addResult($result);
			} catch (Exception $ex) {
				$result = new Notice($ex);
				$result->log();
				// $renderable = $content; // Invalidate the Action which failed and replace it with static content.
				$content = "<div class=\"container\"><h2>System error</h2><p>A system error occurred processing action '$actionClass'. The operation failed and did not complete.</p>\n".
					($_ENV['PHASE'] != 'prod' ? HTMLLogger::renderException($ex) :
						"<p>Please notify your system service provider for help; Provide them with this error code when you do: $GLOBALS[requestId]</p>") .
					"\n".'<button type="button" onclick="location.reload(true)">Click here to reset</button> or select any option from the menu above.'."\n</div>\n";
				$R->addResult($result);
			}

		} else if (is_string($renderable))
			$content = $renderable;

		if (count($noticeList = $R->renderNotices())) { // assignment intended
			$R->addStyles(<<<css
div#NoticeBoard {
	position:fixed; right:1em; bottom:1em;
	height:auto; max-height:6in;
	overflow-y:auto; text-overflow:wrap;
	width:6in; max-width:80%;
	margin:20px auto; padding:1em 1em 0;
	background-color:rgba(200,200,255,.3);
	border:none;
	border-radius:1em;
	z-index:99999;
	text-align:right;
}
div#NoticeBoard .notice {
	text-align:justify;
}
css
				, 'Action Result Notices');
			// What we really want here is a notice window which can be collapsed manually or on a timer, but can be recalled with another click if any notices within it remain.
			$notices = '<div class="notices" id="NoticeBoard">' .
				'<button type="button" class="btn btn-link btn-default" onclick="this.parentNode.style.display=\'none\'">Clear</button>'.
				implode("\n", $noticeList) .'</div>';
		}
		$R->content = <<<html
<nav class="navbar navbar-inverse navbar-fixed-top" role="navigation">
<div class="navbar-header">
    <button type="button" class="navbar-toggle" data-toggle="collapse" data-target="#mainnav"><span class="sr-only">Toggle navigation</span></button>
	<a class="navbar-brand" href="$homeTarget" tabindex="10001">$apptitle</a>
</div>
<div class="collapse navbar-collapse" id="mainnav">
$menus
</div>
</nav>
$notices
$content
<footer>
<div class="container" id="footer-container">
<hr class="page"/>
$R->copyright
</div>
</footer>
html;
		return $R;
	}

	// The mission here is to figure out and prepare what the caller must have in order to render
	// We set the answer (an action object or a renderable string) in $this->renderables[$topSel] as a side effect
	// We return the selection (string of _-sep numbers) which maps out the selection path down through the menu structure to select what's actually been identified
	private function set_renderable( Context $c, array $node, $selection )
	{
		list($type, $label, $role, $options) = $node;

		if ($role && !$c->isAuthorized($role))
			return $this->selections[$this->active];

		// Only actions and menus are valid navigational nodes right now and of these only actions are renderable.
		// Later on we could handle content types or perhaps invent some others.

		list($me, $sub, $subs) = explode('_', $selection, 3);

		if ('menu' == $type && is_numeric($sub) && is_array($options[$sub]))
			return "{$me}_". $this->set_renderable($c, $options[$sub], "{$sub}_$subs");

		if ('action' == $type && is_array($options)) {
			list($actionClass, $args) = $options;
			if (class_exists($actionClass) && is_subclass_of($actionClass, 'Action')) {
				if ($action = new $actionClass($c, $args)) { // assignment intended;
					$this->renderables[$this->active] = $action; // We found what we want to render for this top!
					return $me;
				}
			}
		}
		return $this->selections[$this->active]; // This basically means "don't change a thing from what it had been because we failed to find something renderable!"
	}

	/**
	* Here we are handed a list array of menu nodes within the main navigation.
	* We are rendering our nodes as appropriate while consuming the $selections array (as we walk down the possibly matching selection) to prevent re-rendering the same trigger
	* We are building the $selectors array which represents a single downline map of where we are; this lets us build accurate triggers for each node.
	* @param array $options Menu option list array as described
	* @param string $selections The currently active menu selection in string form (_-sep).
	* @param array $selectors An accumulating list of navigational choices to arrive at the current node. Used in node triggers.
	* @return string HTML rendering of the inside contents of a menu structure. Begins with an <li>...</li>
	*/
	protected function render_menuoptions( Context $c, array $options, $selections, array $selectors )
	{
		is_string($selections) && list($selected, $subSelections) = explode('_', $selections, 2);
		foreach ($options as $optSel=>$option) {
			list($type, $label, $role, $suboptions) = $option;
			if ($role && !$c->isAuthorized($role))
				continue;
			$selectors[] = $optSel;
			if (is_array($selections)) { // This only happens at the top level wherein we let seleted
				list($top, $subSelections) = explode('_', $selections[$optSel], 2);
				$active = ($optSel == $this->active)/* ? ' class="active"' : null*/;
			} else
				$active = (is_string($selections) && $optSel == $selected)/* ? ' class="active"' : null*/;
			$selTarget = htmlentities($c->target(null, false, '_', $selPath = implode('_', $selectors)));
			$tabindex = 'tabindex="'. (is_array($selections) ? $optSel + 10001 : -1) .'"';
			if ('menu'==$type)
				$node = "<li class=\"dropdown-submenu". ($active ? " active" : null) ."\"><a $tabindex href=\"". (strlen($subSelections) ? $selTarget : '#') ."\">$label</a>\n".
					"<ul class=\"dropdown-menu\" role=\"menu\">\n". self::render_menuoptions($c, $suboptions, $subSelections, $selectors) ."</ul></li>\n";
			else if ('action'==$type)
				$node = "<li". ($active?' class="active"':null) ."><a $tabindex href=\"$selTarget\">". htmlentities($label) ."</a></li>\n";
			else if ('divider'==$type)
				$node = '<li class="divider">-</li>'."\n";
			array_pop($selectors);
			$result .= $node;
		}
		return $result;
	}

	public function __construct( Context $root )
	{
		parent::__construct($root);
		$this->active = 0;
	}

	protected static function get_styles( )
	{
		if ($_ENV['PHASE'] == 'dev')
			$phaseColor = 'darkgreen';
		else if ($_ENV['PHASE'] == 'test')
			$phaseColor = 'darkred';
		else
			$phaseColor = 'darkslateblue';
		return <<<css
body {
  padding-bottom: 20pt;
  font-size:11pt;
}
.container table { margin-bottom:0; }
div#body-container { padding-top:1em; }
@media print {
	body { padding:0 }
	header { position:static; }
	div.container { position:static }
	nav { display:none; }
	.btn { display:none; }
}
.navbar-inverse {
  background-color:$phaseColor;
}
.navbar-brand {
  font-weight:bold;
  font-size:larger;
}
nav.navbar {
	font-size:larger;
	margin:0;
}
.navbar-inverse .navbar-brand, .navbar-inverse .navbar-nav > li > a {
	color: #CCC;
}
.navbar-fixed-top {
	padding-right: 10pt;
}
.elementSelector {
	font-weight: bold;
}
.form-control {
	font-size: inherit;
	height: auto;
}
.identifying { font-size:110%; }
input.identifying { box-shadow:0 0 5pt 5pt #DDFFDD; }
select.identifying { box-shadow:0 0 5pt 5pt #DDFFDD; }

.btn-link { padding:0; font-size:110%; }

.dropdown-submenu{position:relative;}
.dropdown-submenu>.dropdown-menu{
	top:0;left:100%; margin-top:-6px;margin-left:-1px;
	-webkit-border-radius:0 6px 6px 6px;-moz-border-radius:0 6px 6px 6px;border-radius:0 6px 6px 6px;}
.nav-tabs>.dropdown-submenu>.dropdown-menu{ top:100%;left:0 }
.dropdown-submenu:hover>.dropdown-menu{display:block;}
.dropdown-menu>.dropdown-submenu>a:after{
	display:block; content:" "; float:right; width:0;height:0; margin-top:5px;margin-right:-10px;
	border-color:transparent;border-style:solid;border-width:5px 0 5px 5px;border-left-color:#cccccc;}

div#NoticeBoard .notice {
}

css;
	}

}
