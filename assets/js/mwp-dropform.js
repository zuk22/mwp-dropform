(function( $ ) {
	'use strict';

	$( document ).ready(function() {

		Dropzone.autoDiscover = false; // Disable auto discover to prevent Dropzone being attached twice
		
		// init DropzoneJS
		var myDropzone = new Dropzone("div#mwp-dropform-uploder", {
			
			url: mwp_dropform_cntrl.upload_file,
			params: {
				'mwp-dropform-nonce': $('#mwp-dropform-nonce').val()
			},
			paramName: "mwp-dropform-file", // name of file field
			acceptedFiles: 'image/*', // accepted file types
			maxFilesize: 2, // MB
			addRemoveLinks: true,

			//success file upload handling
			success: function (file, response) {
				// handle your response object
				console.log(response.status);
				
				file.previewElement.classList.add("dz-success");
				file['attachment_id'] = response.attachment_id; // adding uploaded ID to file object
			},

			//error while handling file upload
			error: function (file,response) {
				file.previewElement.classList.add("dz-error");
			},

			// removing uploaded images
			removedfile: function(file) {
				var _ref;  

				// AJAX request for attachment removing
				$.ajax({
					type: 'POST',
					url: mwp_dropform_cntrl.delete_file,
					data: {
						'attachment_id': file.attachment_id,
						'mwp-dropform-nonce': $('#mwp-dropform-nonce').val()
					},
					// handle response from server
					success: function (response) {
						// handle your response object
						console.log(response.status);
					},
				});
				
				return (_ref = file.previewElement) != null ? _ref.parentNode.removeChild(file.previewElement) : void 0;        
			}
		});
		
	});

})( jQuery );
