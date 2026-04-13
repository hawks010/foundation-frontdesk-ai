<?php
$bot_name = esc_html( fnd_conversa_get_option('bot_name','Conversa') );
$color    = esc_attr( fnd_conversa_get_option('brand_color','#4F46E5') );
$avatar   = esc_url( fnd_conversa_get_option('bot_avatar','') );
$show_contact = (bool) fnd_conversa_get_option('enable_contact', true);
?>
<div class="fnd-conversa-wrapper" data-color="<?php echo $color; ?>">
	<div class="fnd-conversa-window" aria-live="polite">
		<div class="fnd-conversa-header">
			<?php if ( $avatar ) : ?><img class="fnd-conversa-avatar" src="<?php echo $avatar; ?>" alt="<?php echo $bot_name; ?>" /><?php endif; ?>
			<strong><?php echo $bot_name; ?></strong>
		</div>
		<div class="fnd-conversa-messages" id="fnd-conversa-messages"></div>
		<form class="fnd-conversa-input" id="fnd-conversa-form">
			<input type="text" id="fnd-conversa-message" placeholder="Type your message…" aria-label="Your message" />
			<button type="submit"><?php esc_html_e('Send','foundation-conversa'); ?></button>
		</form>
		<?php if ( $show_contact ) : ?>
		<button class="fnd-conversa-contact-toggle" id="fnd-conversa-contact-toggle"><?php esc_html_e('Contact us','foundation-conversa'); ?></button>
		<form class="fnd-conversa-contact" id="fnd-conversa-contact" style="display:none;">
			<input type="text" id="fnd-contact-name" placeholder="Your name" />
			<input type="email" id="fnd-contact-email" placeholder="Your email" />
			<textarea id="fnd-contact-message" placeholder="Your message"></textarea>
			<button type="submit"><?php esc_html_e('Send message','foundation-conversa'); ?></button>
		</form>
		<?php endif; ?>
	</div>
</div>