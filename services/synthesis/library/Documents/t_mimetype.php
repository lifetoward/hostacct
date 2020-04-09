<?php
/**
<type name="mimetype" render="doc/types:mimetype_type">
	<!-- Note: you can do extension to mime-type and even magic mime-type determination via a query to:
		http://filext.com/file-extension/{ext} ; For now we're just stashing there whatever PHP can give us
		on a file upload. -->
		<storage mysql="VARCHAR(255)"/>
		</type>


function mimetype_type( $c, $f, $d )
{
	capcel_load_ext('base/types');
	return string_type($c, $f, $d );
}

*/
abstract public class t_mimetype extends Type
{
}
