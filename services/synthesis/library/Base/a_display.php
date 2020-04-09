<?php
/**
* a_Display
* Provides a general purpose page for displaying an Element in its full relevant detail.
*
* Created: 11/28/14 for Lifetoward LLC
*
* All original code.
* @package Synthesis/Base
* @author Biz Wiz <bizwiz@SyntheticWebApps.com>
* @copyright (c) 2014 Lifetoward LLC; All rights reserved.
* @license proprietary
*/
class a_Display extends Action
{
	protected $focus; // Persistent action properties are handled like this
	private static $savedProperties = array('focus');
	use SerializationHelper;

	public function __construct( Context $c, $args = null )
	{
		parent::__construct($c);
		// We require a focal Element specification in the args.
		// We accept the entire parameter as an Element, or
		//	'class' and 'id' attributes within the args array, or
		//  'focus' as an instantiated object within the args array, or
		//	'focus' as an Element handle string.
		if (is_array($args)) {
			if (is_subclass_of($args['class'], 'Element') && $args['id'] > 0)
				$this->focus = $args['class']::get($args['id']);
			else if (is_object($args['focus']) && is_a($args['focus'], 'Element'))
				$this->focus = $args['focus'];
			else if (is_string($args['focus'])) {
				list($class,$id) = explode('=', $args['focus']);
				if (is_subclass_of($class, 'Element'))
					$this->focus = $class::get($id);
			}
		} else if (is_subclass_of($args, 'Element')) {
			$this->focus = $args;
		}
		if (!is_a($this->focus, 'Element'))
			throw new ErrorException("Failed to identify a focal Element from the arguments provided.");
	}

	protected function render_me( Result $returned = null )
	{
		$c = $this->context; // this is just for shorthand... our parent set this up for us.
		extract($c->request, EXTR_PREFIX_INVALID, 'r');

		$R = new HTMLRendering($c, $returned, $this->focus); // Accumulate results into this object
		$focalClass = get_class($this->focus);
		$hints = $focalClass::$hints[__CLASS__];
		$focalFields = $focalClass::getFieldDefs();
		$this->focus->_rendering = $R;
		$R->mode = $R::VERBOSE;

		// First we assemble the banner consisting of a primary title, optional subtitle, and optional description of the INSTANCE.
		// This information is drawn from the fields specified in the hints as title, subtitle, and headdesc.
		$subtitle = '<small>&nbsp;: '. (
			($fn = $hints['subtitle']) ? // assignment intended
				(is_array($fd = $focalFields[$hints['subtitle']]) ? $this->focus->{».$fn} : $fn) :
				$focalClass::$descriptive
			) ."</small>\n";
		$title = '<h1>'.
			( ($fn = $hints['title']) ? // assignment intended
				(is_array($fd = $focalFields[$hints['title']]) ? $this->focus->{».$fn} : $fn) :
				$this->focus->_rendered
			) ."$subtitle</h1>\n";
		if ($fn = $hints['description'])
			$description = '<p class="help-block">'. (is_array($fd = $focalFields[$fn]) ? $this->focus->{».$fn} : $fn) ."</p>";
		$R->header = "$title\n$description";
		// Next we render an action or menu bar for actions to be taken against the focal instance.
		// TBD

		// Finally we break the viewing area into 2 columns (for readability) and render tiles into them as we read tile specifications from the hints.
		// What goes in each tile is taken from the static configuration of the Element subclass in the hints for this Action under the 'tiles' key, which contains a list of tile specification arrays.
		// Each conventional tile specification consists of an array of keyed values which supply hints to the tile rendering method. One of those hints must be the name of the method to use:
		//	i.e. array('method'=>'methodName', [ ... other hints for tile method ... ] )
		// The signature of a tile rendering method must be: "static function methodName( Instance $focus, array $tileHintArray, HTMLRendering $R );" where the tile hint array is the one described here above.
		// Some commonly useful methods are implemented here like fields, relations, and relatives. There's also the formatting helper rowbreak. Each tile method has its own parameter requirements.
		$tiles = is_array($hints['tiles']) ? $hints['tiles'] :
			[ [ 'method'=>'a_Display::fields', 'title'=>$focalClass::$descriptive, 'include'=>array_keys($focalFields), 'operations'=>['head'=>'update'] ] ];
		$R->addStyles("div.DisplayTile h3 { margin:6pt 0 }", 'DisplayTiles');
		$content .= '<div class="DisplayTileRow row">'."\n";
		foreach ($tiles as $tile)
			$content .= '<div class="col-md-'. ($tile['width'] > 0 && $tile['width'] <= 12 ? $tile['width'] : 6) .'"><div class="DisplayTile">'.
					"\n". call_user_func($tile['method'], $this->focus, $tile, $R) ."</div></div>\n";
		$R->content = "$content</div>\n";

		$R->addStyles(<<<css
h1 { /* Focus title */ }
h2 { /* Section title */ }
h3 { /* Tile title */ }
h4 { /* Focus subtitle */ }
div.DisplayBannerRow { }
div.DisplayTileRow { margin-left: auto; margin-right:auto; }
div.DisplayTile { border: 2px outset gray; border-radius:.5em; padding:.5em; margin-bottom:1em;}
.DisplayTile label { color:darkslateblue; font-size:smaller; margin-top:.8em; margin-bottom:0 }
.DisplayTile hr { margin:0 }
.DisplayFieldValue { padding-left:1em }
span.FieldsTileTriggers { float:right; }
css
			, __CLASS__);

		return $R;
	}

	/**
	* Render fields from the primary Element in focus. Specific fields can be identified and titled as a group, one such group per tile.
	* @return string The content to render INSIDE the tile. The tile itself is for the caller to render.
	*/
	protected static function fields( Instance $focus, array $hints, HTMLRendering $R )
	{
		if (count($fieldlist = $focus::getFieldList($R, (array)$hints['exclude'], (array)$hints['include']))) {
			$R->mode = $R::VERBOSE; $focus->_rendering = $R;
			foreach ($fieldlist as $fn)
				if (($val = $focus->$fn) || is_numeric($val) || is_bool($val)) // we ignore empty values
					$fields .= "<label class=\"FieldsLabel\" for=\"$fn\">{$focus->{¬.$fn}}</label><div id=\"$fn\" class=\"DisplayFieldValue\">{$focus->{».$fn}}</div>\n";
			if ($fields)
				$R->addStyles("div.DisplayFieldValue { margin: 0 }", __CLASS__.'_FieldValue');
			else
				$fields = "No information.";

			if ($hints['operations']['head']) {
				$tileTriggers = '<span class="FieldsTileTriggers">';
				foreach ((array)$hints['operations']['head'] as $op)
					$tileTriggers .= " ". $R->renderOperationAsIcon($focus->getOperation($op, $R->context, '_action', $hints));
				$tileTriggers .= "</span>";
			}
		}
		$title = $hints['title'] ? "<h3>$hints[title]</h3><hr style=\"margin:6pt 0\"/>" : null;
		return <<<html
$tileTriggers
$title
$fields
html;
	}

	/**
	* Render Elements of a defined Class which refer to our focus by a defined field.
	* @return string The content to render INSIDE the tile. The tile itself is for the caller to render.
	*/
	protected static function referents( Instance $focus, array $hints, HTMLRendering $R )
	{
		return "<p>Not yet implemented.</p>";
	}

	/**
	* Render the relation records currently associated with the focal Element for a specified Relation subclass.
	* This approach is oriented toward a data-ful or at least heavier semantic.
	* We ALWAYS render one relative per row, and the rows MAY have data fields depending on the hints.
	* @return string The content to render INSIDE the tile. The tile itself is for the caller to render.
	*/
	protected static function relatives( Instance $focus, array $hints, HTMLRendering $R )
	{
		return self::commonRelTile(compact('focus','hints','R'),
			function (array $vars) {
				extract($vars);
				foreach ($hints['class']::getRelativesList($focus, (array)$hints['queryArgs']) as $rel)
					$rels[] = $rel->render($R);
				return count($rels) ? implode(", ", $rels) : null;
			});
	}

	/**
	* List the relatives associated with the focal Element through the specified Relation subclass.
	* This approach is designed to ignore any data in the relation record itself and only renders Relatives by their identifying name (_rendered).
	* It should attempt to prevent breaking a single relative label across multiple rows if it can.
	* @return string The content to render INSIDE the tile. The tile itself is for the caller to render.
	*/
	protected static function relations( Instance $focus, array $hints, HTMLRendering $R )
	{
		return self::commonRelTile(compact('focus','hints','R'),
			function (array $vars) {
				extract($vars);
				foreach ($hints['class']::getRelations($focus, (array)$hints['queryArgs']) as $relrec) {
					if (($cols = count($relfields = $relrec->renderFields($R, $hints['exclude'], $hints['include']))) < 2) // assignment intended
                        $cols = 2;
                    if ($hints['headfield'] && ($headfield = $relrec->renderField($hints['headfield'], $R))) { // assignment intended
                        $cols--;
                        $headfield = "<td align=\"right\">$headfield</td>";
                    } else
                        $headfield = null;
					$relrows[] = "<tbody><tr><th colspan=\"$cols\">". $relrec->_relative->render($R) ."</th>$headfield</tr>".
                        "<tr><td>". implode("</td>\n<td>", $relfields) ."</td></tr></tbody>";
				}
				return count($relrows) ? '<table class="table table-striped table-hover table-responsive">'. implode("\n", $relrows) ."</table>" : null;
			});
	}

	/**
	* The following helper simply consolidates the hugely common aspects of rendering standard relation-based tiles.
	*/
	private static function commonRelTile( array $args, $contentRenderer )
	{
		if (!is_subclass_of($class = $args['hints']['class'], 'Relation'))
			throw new Exception("Method '". __FUNCTION__ ."' requires a Relation 'class' hint. We got '$class'.");
		extract($vars = array_merge($args, $args['hints']['class']::obtainRenderingVars($args['hints'])));
		if ($refclass != get_class($focus))
			throw new Exception("Relation class '$class' expects the referent to be of class '$refclass', but our focus is '$x'.");
		if ($hints['operations']['head']) {
			foreach ((array)$hints['operations']['head'] as $op)
				$accum .= " ". $R->renderOperationAsIcon((array)$class::getClassOperation($focus, $op, $R->context, '_action', $hints));
			$headTriggers = '<span class="FieldsTileTriggers">'."$accum</span>";
		}
		$title = "<h3>". ($hints['title'] ? htmlentities($hints['title']) : $relatives) ."</h3><hr/>";
		if (!($content = $contentRenderer($vars)))
			$content = '<p style="margin-top:0.5em">'.
				(($opspec = $class::getClassOperation($focus, 'select', $R->context, '_action', $hints)) ?
					$R->renderOperationAsLink(array_merge($opspec,['glyphicon'=>"Click here to"])) :
					"No $relatives."
				). "</p>";
		return "$headTriggers\n$title\n$content\n";
	}

}
