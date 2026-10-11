jQuery(document).ready(function($) {

    // --- Author photo upload (WordPress media library) ---
    var customUploader;
    var attachPhotoUploaders = {};

    function maeNormalizeAttachId(id) {
        if (id === undefined || id === null || id === '' || id === 0 || id === '0') {
            return '';
        }
        return String(id);
    }

    function maeNormalizeAttachUrl(url) {
        if (url === undefined || url === null) {
            return '';
        }
        return String(url).trim();
    }

    function maeAttachFieldPrefix(prefix) {
        return prefix || 'attach_photo';
    }

    function maeAttachPreviewId(prefix, index) {
        return '#' + String(prefix).replace(/_/g, '-') + '-' + index + '-preview';
    }

    function maeSetAttachPhotoPreview(index, id, url, prefix) {
        index = String(index);
        prefix = maeAttachFieldPrefix(prefix);
        id = maeNormalizeAttachId(id);
        url = maeNormalizeAttachUrl(url);

        var $idField = $('#' + prefix + '_' + index + '_id');
        var $urlField = $('#' + prefix + '_' + index + '_url');
        var $preview = $(maeAttachPreviewId(prefix, index));

        if (!$idField.length || !$urlField.length || !$preview.length) {
            console.warn('Attach image fields missing for', prefix, index);
            return;
        }

        $idField.val(id);
        $urlField.val(url);

        if (url) {
            $preview.html(
                '<img src="' + url + '" alt="Image #' + index + ' Preview" style="max-width: 150px; height: auto; display: inline-block; margin-bottom: 5px;" />' +
                '<button type="button" class="remove-attach-photo text-red-500 hover:text-red-700 text-xs mt-1 rounded-none block mx-auto" data-attach-prefix="' + prefix + '" data-photo-index="' + index + '">Remove Image</button>'
            ).show();
        } else {
            $preview.hide().empty();
        }
    }

    function maeFillAttachPhotosFromData(data) {
        if (!data) {
            return;
        }
        for (var n = 1; n <= 3; n++) {
            maeSetAttachPhotoPreview(
                n,
                data['attach_photo_' + n + '_id'],
                data['attach_photo_' + n + '_url'],
                'attach_photo'
            );
            $('#' + 'attach_photo_' + n + '_link').val(data['attach_photo_' + n + '_link'] || '');

            maeSetAttachPhotoPreview(
                n,
                data['attach_foot_' + n + '_id'],
                data['attach_foot_' + n + '_url'],
                'attach_foot'
            );
            $('#' + 'attach_foot_' + n + '_link').val(data['attach_foot_' + n + '_link'] || '');

            maeSetAttachPhotoPreview(
                n,
                data['attach_right_' + n + '_id'],
                data['attach_right_' + n + '_url'],
                'attach_right'
            );
            $('#' + 'attach_right_' + n + '_link').val(data['attach_right_' + n + '_link'] || '');
        }
    }

    function maeGetCheckedPlacements() {
        return $('.caast-placement-checkbox:checked').map(function() {
            return $(this).val();
        }).get();
    }

    function maeSetPlacements(placements) {
        if (!Array.isArray(placements)) {
            placements = placements ? [placements] : [];
        }
        if (!placements.length) {
            placements = ['home-left-section-1'];
        }
        $('.caast-placement-checkbox').each(function() {
            $(this).prop('checked', placements.indexOf($(this).val()) !== -1);
        });
        maeSyncAttachConfigByPlacement();
    }

    function maeSyncAttachConfigByPlacement() {
        var placements = maeGetCheckedPlacements();
        $('.caast-attach-config').each(function() {
            var $panel = $(this);
            if (placements.indexOf($panel.data('placement')) !== -1) {
                $panel.show();
            } else {
                $panel.hide();
            }
        });
    }

    // Expose for AI/draft load path.
    window.maeSetAttachPhotoPreview = maeSetAttachPhotoPreview;
    window.maeFillAttachPhotosFromData = maeFillAttachPhotosFromData;
    window.maeSyncAttachConfigByPlacement = maeSyncAttachConfigByPlacement;
    window.maeSetPlacements = maeSetPlacements;

    maeSyncAttachConfigByPlacement();
    $(document).on('change', '.caast-placement-checkbox', maeSyncAttachConfigByPlacement);

    // Add / Replace: mutually exclusive; default Add.
    function maeSyncPublishModeChecks($changed) {
        var $add = $('#publish_mode_add');
        var $replace = $('#publish_mode_replace');
        if (!$add.length || !$replace.length) {
            return;
        }
        if ($changed && $changed.is('#publish_mode_replace') && $replace.is(':checked')) {
            $add.prop('checked', false);
        } else if ($changed && $changed.is('#publish_mode_add') && $add.is(':checked')) {
            $replace.prop('checked', false);
        }
        if (!$add.is(':checked') && !$replace.is(':checked')) {
            $add.prop('checked', true);
        }
    }
    $(document).on('change', '#publish_mode_add, #publish_mode_replace', function() {
        maeSyncPublishModeChecks($(this));
    });
    maeSyncPublishModeChecks();

    $(document).on('click', '.browse-author-photo', function(e) {
        e.preventDefault();

        if (typeof wp === 'undefined' || !wp.media) {
            alert('Media library is not available. Please reload the page and try again.');
            return;
        }

        if (customUploader) {
            customUploader.open();
            return;
        }

        customUploader = wp.media({
            title: 'Select or Upload Author Photo',
            button: {
                text: 'Select Photo'
            },
            library: {
                type: 'image'
            },
            multiple: false
        });

        customUploader.on('select', function() {
            var attachment = customUploader.state().get('selection').first().toJSON();
            $('#author_photo_id').val(attachment.id);
            $('#author_photo_url').val(attachment.url);
            $('#author-photo-preview').html(
                '<img src="' + attachment.url + '" alt="Author Photo Preview" style="max-width: 150px; height: auto; display: block; margin: 0 auto 5px;" />' +
                '<button type="button" class="remove-author-photo text-red-500 hover:text-red-700 text-xs mt-1 rounded-none block mx-auto">Remove Image</button>'
            ).show();
        });

        customUploader.open();
    });

    $(document).on('click', '.remove-author-photo', function(e) {
        e.preventDefault();
        $('#author_photo_id').val('');
        $('#author_photo_url').val('');
        $('#author-photo-preview').hide().html('');
    });

    // Attach Image #1 / #2 / #3 (section + foot)
    $(document).on('click', '.browse-attach-photo', function(e) {
        e.preventDefault();
        var index = String($(this).data('photo-index') || '');
        var prefix = maeAttachFieldPrefix($(this).data('attach-prefix'));
        var uploaderKey = prefix + '_' + index;

        if (!index) {
            return;
        }

        if (typeof wp === 'undefined' || !wp.media) {
            alert('Media library is not available. Please reload the page and try again.');
            return;
        }

        if (attachPhotoUploaders[uploaderKey]) {
            attachPhotoUploaders[uploaderKey].open();
            return;
        }

        attachPhotoUploaders[uploaderKey] = wp.media({
            title: 'Select or Upload Image #' + index,
            button: {
                text: 'Upload Image #' + index
            },
            library: {
                type: 'image'
            },
            multiple: false
        });

        attachPhotoUploaders[uploaderKey].on('select', function() {
            var attachment = attachPhotoUploaders[uploaderKey].state().get('selection').first().toJSON();
            maeSetAttachPhotoPreview(index, attachment.id, attachment.url, prefix);
        });

        attachPhotoUploaders[uploaderKey].open();
    });

    $(document).on('click', '.remove-attach-photo', function(e) {
        e.preventDefault();
        var index = String($(this).data('photo-index') || '');
        var prefix = maeAttachFieldPrefix($(this).data('attach-prefix'));
        if (index) {
            maeSetAttachPhotoPreview(index, '', '', prefix);
        }
    });


    // --- Load existing author content ---
    $('#load-author-content-btn').on('click', function(e) {
        e.preventDefault();
        var selectedAuthorId = $('#selected_author_id').val();
        
        if (selectedAuthorId) {
            $.ajax({
                url: maeV3EditorAjax.ajaxurl,
                type: 'POST',
                dataType: 'json',
                data: {
                    action: 'mae_v3_load_content',
                    nonce: maeV3EditorAjax.nonce,
                    post_id: selectedAuthorId
                },
                beforeSend: function() {
                    Swal.fire({
                        title: 'Loading...',
                        text: 'Loading author data, please wait.',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data || {};
                        
                        $('#author_post_id').val(selectedAuthorId);
                        $('#selected_author_id').val(String(selectedAuthorId));
                        $('#new_author_name').val(data.post_title || '');
                        $('#author_subtitle').val(data.author_subtitle || '');
                        $('#footer_position').val(data.footer_position || '');
                        $('#insert_link').val(data.insert_link || '');
                        maeSetPlacements(data.placement_category);
                        $('#author_category').val(data.author_category || '').trigger('change');
                        $('#author_level').val(data.author_level || '').trigger('change');

                        var bodyContent = data.post_content || data.author_body_content || '';
                        if (typeof tinymce !== 'undefined' && tinymce.get('author_content')) {
                            tinymce.get('author_content').setContent(bodyContent);
                        } else if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
                            tinymce.activeEditor.setContent(bodyContent);
                        } else {
                            $('#author_content').val(bodyContent);
                        }
                        
                        if (data.author_photo_url) {
                            $('#author_photo_id').val(data.author_photo_id || '');
                            $('#author_photo_url').val(data.author_photo_url);
                            $('#author-photo-preview').html('<img src="' + data.author_photo_url + '" alt="Author Photo Preview" style="max-width: 150px; height: auto; display: block; margin: 0 auto 5px;" /><button type="button" class="remove-author-photo text-red-500 hover:text-red-700 text-xs mt-1 rounded-none block mx-auto">Remove Image</button>').show();
                        } else {
                            $('#author_photo_id').val('');
                            $('#author_photo_url').val('');
                            $('#author-photo-preview').hide().html('');
                        }

                        maeFillAttachPhotosFromData(data);

                        var submitAction = 'save';
                        if (data.post_status === 'publish') {
                            submitAction = 'publish';
                        } else if (data.post_status === 'archive') {
                            submitAction = 'archive';
                        }
                        $('#submit_author_action_select').val(submitAction);
                        
                        Swal.close();
                        Swal.fire({
                            icon: 'success',
                            title: 'Successfully!',
                            text: 'Author content has been successfully loaded.',
                            confirmButtonText: 'OK'
                        });
                    } else {
                        Swal.close();
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed to Load',
                            text: response.data,
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function() {
                    Swal.close();
                    Swal.fire({
                        icon: 'error',
                        title: 'Failed',
                        text: 'An AJAX error occurred.',
                        confirmButtonText: 'OK'
                    });
                }
            });
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Warning!',
                text: 'Please select the author from the dropdown first.',
                confirmButtonText: 'OK'
            });
        }
    });

    // Form submit: sync TinyMCE, confirm delete/placement actions.
    $('#author-editor-form').on('submit', function(e) {
        if (typeof tinymce !== 'undefined' && tinymce.get('author_content')) {
            tinymce.get('author_content').save();
        } else if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
            tinymce.activeEditor.save();
        }

        var form = this;
        var submitter = e.originalEvent && e.originalEvent.submitter ? e.originalEvent.submitter : null;
        var submitName = submitter ? submitter.name : '';
        var submitValue = submitter ? submitter.value : '';
        var action = $('#submit_author_action_select').val();

        if (submitName === 'submit_author_action_btn' && action === 'delete') {
            e.preventDefault();
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to undo this delete.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Yes, delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
            return;
        }

        if (submitName === 'placement_action' && submitValue === 'publish_placement') {
            e.preventDefault();
            var authorPostId = $('#author_post_id').val();
            var $checked = $('.caast-placement-checkbox:checked');

            if (!authorPostId || authorPostId === '0') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Warning',
                    text: 'Load or save an author profile before publishing to a placement.',
                    confirmButtonText: 'OK'
                });
                return;
            }

            if (!$checked.length) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Warning',
                    text: 'Please tick at least one placement.',
                    confirmButtonText: 'OK'
                });
                return;
            }

            var placementLabels = $checked.map(function() {
                return $.trim($(this).closest('label').text());
            }).get().join(', ');

            Swal.fire({
                title: 'Confirm placement publish',
                text: 'Publish this author to: ' + placementLabels + '?',
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes, publish',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // form.submit() does not send the clicked button, so carry its value explicitly.
                    $(form).find('input[type="hidden"][name="placement_action"]').remove();
                    $('<input>', { type: 'hidden', name: 'placement_action', value: 'publish_placement' }).appendTo(form);
                    form.submit();
                }
            });
        }
    });

    $(document).on('click', '#upload_final_content_to_editor', function(e) {
        e.preventDefault();
        var generatedContent = $('#final_generated_content').val();

        if (typeof tinymce !== 'undefined' && tinymce.get('author_content')) {
            tinymce.get('author_content').setContent(generatedContent);
        } else {
            $('#author_content').val(generatedContent);
        }
    });
});
