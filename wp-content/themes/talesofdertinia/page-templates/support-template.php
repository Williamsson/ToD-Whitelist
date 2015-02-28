<?php
/*
Template Name: Support template
*/
?>
<?php get_header(); ?>



<section class="content">

	

	<?php get_template_part('inc/page-title'); ?>

	

	<div class="pad group">

		

		<?php while ( have_posts() ): the_post(); ?>

		

			<article <?php post_class('group'); ?>>

				

				<?php get_template_part('inc/page-image'); ?>

				

				<div class="entry themeform">
					
					<?php 
						if(is_user_logged_in()){
							the_content();
						}else{
							echo "You need to <a href='http://dev.talesofdertinia.com/wp-login.php'>login</a> or <a href='http://dev.talesofdertinia.com/wp-login.php?action=register'>register an account</a> to create a support ticket!";
						}
					?>

					<div class="clear"></div>

				</div><!--/.entry-->

				

			</article>

			

			<?php if ( ot_get_option('page-comments') == 'on' ) { comments_template('/comments.php',true); } ?>

			

		<?php endwhile; ?>

		

	</div><!--/.pad-->

	

</section><!--/.content-->



<?php get_sidebar(); ?>



<?php get_footer(); ?>