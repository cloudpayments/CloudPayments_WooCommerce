jQuery(document).ready(function($) {

	var cp = new ClipboardJS( '.copy-cloudpayments-action-url' );
	var successTimeout;

	cp.on( 'success', function( event ) {
		var triggerElement = $( event.trigger ),
			successElement = $( '.success', triggerElement.closest( '.copy-to-clipboard-container' ) );

		// Clear the selection and move focus back to the trigger.
		event.clearSelection();
		// Handle ClipboardJS focus bug, see https://github.com/zenorocha/clipboard.js/issues/680
		triggerElement.trigger( 'focus' );

		// Show success visual feedback.
		successElement.removeClass( 'hidden' );

		// Hide success visual feedback after 3 seconds since last success.
		setTimeout( function() {
			successElement.addClass( 'hidden' );
		}, 3000 );
	} );
});
