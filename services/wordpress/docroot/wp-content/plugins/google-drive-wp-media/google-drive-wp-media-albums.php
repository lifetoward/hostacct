<?php 
if(!function_exists('is_admin')){
     die('You do not have sufficient permissions to access this page.');
}
if ( !is_admin() ) {
     wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
}
?>
<ol start="1">
		<?php
			$terms_album = get_terms( 'gdwpm_album' );
			if ( ! empty( $terms_album ) && ! is_wp_error( $terms_album ) ){
				foreach ( $terms_album as $term ) {
		?>		
		<li><?php echo $term->name . ' <a href="' . get_term_link( $term ) . '" title="View ' . $term->name . ' in a new window" target="_blank">(' . $term->count . ' Galleries)</a>';?></li>				
		<?php 	}
			}else{ ?>
	<li>Uncategorized</li>				
		<?php } ?>	
</ol>