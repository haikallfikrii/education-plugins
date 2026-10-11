jQuery(document).ready(function($) {
    // Media Uploader for Book Cover
    var bookCoverUploader;

    $(document).on('click', '.browse-book-cover', function(e) {
        e.preventDefault();

        // If the uploader already exists, reopen it
        if (bookCoverUploader) {
            bookCoverUploader.open();
            return;
        }

        // Create the media frame.
        bookCoverUploader = wp.media.frames.file_frame = wp.media({
            title: 'Choose Book Cover Image',
            button: {
                text: 'Select Cover'
            },
            multiple: false // Set to true to allow multiple files to be selected
        });

        // When a file is selected, grab the URL and set it as the text field's value.
        bookCoverUploader.on('select', function() {
            var attachment = bookCoverUploader.state().get('selection').first().toJSON();
            $('#book_cover_id').val(attachment.id);
            $('#book_cover_url').val(attachment.url);
            $('#book-cover-preview img').attr('src', attachment.url);
            $('#book-cover-preview').show();
        });

        // Open the media dialog
        bookCoverUploader.open();
    });

    // Handle "Remove Image" button click for Book Cover
    $(document).on('click', '.remove-book-cover', function(e) {
        e.preventDefault();
        $('#book_cover_id').val('');
        $('#book_cover_url').val('');
        $('#book-cover-preview img').attr('src', '');
        $('#book-cover-preview').hide();
    });

    // Function to disable/enable form fields
    function toggleFormFields(disable) {
        $('#book-editor-form').find('input, select, button[type="submit"], textarea').prop('disabled', disable);
        $('#load-book-content-btn').prop('disabled', false);
    }

    // Function to clear all book-related fields
    function clearBookFields() {
        $('#book_post_id').val('0');
        $('#new_book_name').val('');
        $('#book_subtitle').val('');
        if (tinymce.get('book_content')) {
            tinymce.get('book_content').setContent('');
        } else {
            $('#book_content').val('');
        }
        $('#book_cover_id').val('');
        $('#book_cover_url').val('');
        $('#book-cover-preview img').attr('src', '');
        $('#book-cover-preview').hide();
        $('.caast-placement-checkbox').prop('checked', false);
        $('#insert_link').val('');
        $('#book_category').val('');
        $('#book_level').val('');
        // Do NOT clear selected_author_id when loading a book, as it might be relevant.
        // It will be updated by the AJAX response if an author is associated with the loaded book.
    }

    // Function to clear author selection (used if "add new book" is effectively chosen by not selecting an author)
    function clearAuthorSelection() {
         $('#selected_author_id').val('');
    }

    function myBookEditorResolveActionFromStatus(postStatus) {
        if (window.caastWorkflow && typeof window.caastWorkflow.statusToAction === 'function') {
            return window.caastWorkflow.statusToAction(postStatus || 'draft');
        }
        if (postStatus === 'publish') {
            return 'publish';
        }
        if (postStatus === 'archive') {
            return 'archive';
        }
        if (postStatus === 'pending') {
            return 'ready_for_approval';
        }
        if (postStatus === 'approved') {
            return 'approved';
        }
        if (postStatus === 'unpublished' || postStatus === 'private') {
            return 'unpublish';
        }
        return 'draft';
    }

    function myBookEditorSetBookCoverPreview(coverId, coverUrl) {
        if (coverId && coverUrl) {
            $('#book_cover_id').val(coverId);
            $('#book_cover_url').val(coverUrl);
            $('#book-cover-preview').html(
                '<img src="' + coverUrl + '" alt="Book Cover Preview" style="max-width: 150px; height: auto; display: inline-block; margin-bottom: 5px;" />' +
                '<button type="button" class="remove-book-cover text-red-500 hover:text-red-700 text-xs mt-1 rounded-none block mx-auto">Remove Image</button>'
            ).show();
        } else {
            $('#book_cover_id').val('');
            $('#book_cover_url').val('');
            $('#book-cover-preview').hide().html('');
        }
    }

    window.myBookEditorPopulateForm = function(data) {
        if (!data) {
            return;
        }

        $('#book_post_id').val(data.book_post_id || data.post_id || '0');
        if (data.book_name || data.title) {
            $('#new_book_name').val(data.book_name || data.title);
        }
        if (typeof data.book_subtitle !== 'undefined') {
            $('#book_subtitle').val(data.book_subtitle);
        }

        var bodyContent = data.book_content || data.post_content || data.content || '';
        if (typeof tinymce !== 'undefined' && tinymce.get('book_content')) {
            tinymce.get('book_content').setContent(bodyContent);
        } else if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
            tinymce.activeEditor.setContent(bodyContent);
        } else {
            $('#book_content').val(bodyContent);
        }

        myBookEditorSetBookCoverPreview(data.book_cover_id, data.book_cover_url);

        if (typeof data.placement_category !== 'undefined') {
            var placements = Array.isArray(data.placement_category)
                ? data.placement_category
                : (data.placement_category ? [data.placement_category] : []);
            $('.caast-placement-checkbox').each(function() {
                $(this).prop('checked', placements.indexOf($(this).val()) !== -1);
            });
        }
        if (typeof data.insert_link !== 'undefined') {
            $('#insert_link').val(data.insert_link || '');
        }
        if (typeof data.book_categories !== 'undefined') {
            $('#book_category').val(data.book_categories || '');
        }
        if (typeof data.book_levels !== 'undefined') {
            $('#book_level').val(data.book_levels || '');
        }
        if (typeof data.associated_author_id !== 'undefined') {
            $('#selected_author_id').val(data.associated_author_id ? String(data.associated_author_id) : '');
        }

        if (data.post_status) {
            $('#submit_book_action_select').val(myBookEditorResolveActionFromStatus(data.post_status));
        }
    };


    // AJAX to load book content when "Submit" next to "Select a book" is clicked
    $('#load-book-content-btn').on('click', function(e) {
        e.preventDefault();
        var bookId = $('#selected_book_id').val();

        if (bookId) {
            $.ajax({
                url: myBookEditorAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'my_book_editor_get_book_content',
                    book_id: bookId,
                    nonce: myBookEditorAjax.nonce
                },
                beforeSend: function() {
                    toggleFormFields(true); // Disable all form fields
                    $('#load-book-content-btn').text('Loading...');
                },
                success: function(response) {
                    if (response.success) {
                        window.myBookEditorPopulateForm(response.data);

                        if (response.data.message) {
                             if (response.data.book_post_id === 0) {
                                alert(response.data.message);
                             }
                        }

                    } else {
                        alert(response.data.message || 'Error loading book content. Please try again.');
                        clearBookFields();
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    alert('AJAX Error: ' + textStatus + ' - ' + errorThrown + '. Check browser console for details.');
                    console.log(jqXHR.responseText);
                    clearBookFields();
                },
                complete: function() {
                    toggleFormFields(false); // Re-enable form fields
                    $('#load-book-content-btn').text('Submit');
                }
            });
        } else {
            alert(myBookEditorAjax.alert_no_book_selected);
            clearBookFields();
            clearAuthorSelection(); // Clear author too if no book is selected
        }
    });

    // Handle form submission (for actions like delete, publish, save, and new placement_action)
    $('#book-editor-form').on('submit', function(e) {
        // Ensure TinyMCE content is saved to textarea before submission
        if (typeof tinymce != 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
            tinymce.activeEditor.save();
        }

        var submitAction = e.originalEvent.submitter.name; // Get the name of the button that triggered the submit
        var submitValue = e.originalEvent.submitter.value; // Get the value of the button

        if (submitAction === 'submit_book_action_btn' && submitValue === 'delete') {
            if (!confirm(myBookEditorAjax.alert_confirm_delete)) {
                e.preventDefault(); // Prevent form submission if user cancels
            }
        } else if (submitAction === 'placement_action' && submitValue === 'publish_placement') {
            e.preventDefault();
            var form = this;
            var selectedBookId = $('#book_post_id').val();
            var $checked = $('.caast-placement-checkbox:checked');

            if (!selectedBookId || selectedBookId === '0') {
                Swal.fire({
                    icon: 'warning',
                    title: 'Warning',
                    text: 'Please select or load a book before publishing to a placement.',
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
                text: 'Publish this book to: ' + placementLabels + '?',
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

    // Add event listener for when "Add a book" input changes,
    // to clear "Select a book" if a new book name is being entered.
    $('#new_book_name').on('input', function() {
        if ($(this).val().trim() !== '') {
            $('#selected_book_id').val(''); // Clear selected book if a new name is typed
        }
    });

    // Add event listener for when "Select a book" changes,
    // to clear "Add a book" input if an existing book is selected.
    $('#selected_book_id').on('change', function() {
        if ($(this).val().trim() !== '') {
            $('#new_book_name').val(''); // Clear new book name if an existing book is selected
        }
        // When a book is selected, we want to load its content, including author.
        // The AJAX call for #load-book-content-btn is responsible for this.
        // We will trigger that button's click event.
        $('#load-book-content-btn').trigger('click');
    });

    // Add event listener for when "Select an author" changes,
    // to potentially trigger loading of author-related data (though for this context,
    // it's mainly for setting the hidden author ID in the form for submission).
    $('#selected_author_id').on('change', function() {
        // If you had a separate section that displays author details when selected,
        // you would trigger its loading here. For now, the change itself updates the form.
        // If you want to confirm the selection, you can trigger the author load button.
        // $('#load-author-content-btn').trigger('click'); // Uncomment if you want immediate feedback on author selection
    });

    // --- Reusable Upload / Edit / Submit (via MLEUploader) ---
    if (window.MLEUploader && typeof MLEUploader.attach === 'function' && window.myBookEditorAjax) {
        var $bookContainer = $('[data-mle-uploader="book"]');
        if ($bookContainer.length) {
            window.__mleBookUploader = MLEUploader.attach({
                container: $bookContainer.get(0),
                ajaxConfig: { ajaxurl: myBookEditorAjax.ajaxurl, nonce: myBookEditorAjax.nonce },
                actions: {
                    extract: 'mbe_extract_text_from_upload',
                    submit:  'mbe_submit_book_to_wp'
                },
                editorId: 'book_content',
                titleInputId: 'new_book_name',
                bookNameInputId: 'mbe_source_book_name',
                previewSourceId: 'final_generated_content',
                submitExtraFields: function() {
                    var bookPostId = ($('#book_post_id').val() || '').trim();
                    return {
                        book_post_id: bookPostId,
                        subtitle: ($('#book_subtitle').val() || '').trim(),
                        force_new_draft: bookPostId ? '0' : '1'
                    };
                },
                onUploaded: function() {
                    // New file upload should create a separate draft on submit, not overwrite the last saved book.
                    $('#book_post_id').val('0');
                },
                onSubmitted: function(data) {
                    if (data && data.book_post_id) {
                        $('#book_post_id').val(data.book_post_id);
                    }
                    $(document).trigger('mbe:drafts-changed', [data && data.book_post_id]);
                }
            });
        }
    }

    // Mirror AI title into source Book Name field when generation finishes.
    $(document).on('blur change', '#final_generated_title', function() {
        var v = ($(this).val() || '').trim();
        var $bn = $('#mbe_source_book_name');
        if (v && $bn.length && !$bn.val()) {
            $bn.val(v);
        }
    });
});