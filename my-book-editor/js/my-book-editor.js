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
        $('#placement_category').val(''); // Changed from footer_position
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
                        var data = response.data;
                        $('#book_post_id').val(data.book_post_id);
                        $('#new_book_name').val(data.book_name);
                        $('#book_subtitle').val(data.book_subtitle);

                        if (tinymce.get('book_content')) {
                            tinymce.get('book_content').setContent(data.book_content || '');
                        } else {
                            $('#book_content').val(data.book_content || '');
                        }

                        if (data.book_cover_id && data.book_cover_url) {
                            $('#book_cover_id').val(data.book_cover_id);
                            $('#book_cover_url').val(data.book_cover_url);
                            $('#book-cover-preview img').attr('src', data.book_cover_url);
                            $('#book-cover-preview').show();
                        } else {
                            $('#book_cover_id').val('');
                            $('#book_cover_url').val('');
                            $('#book-cover-preview img').attr('src', '');
                            $('#book-cover-preview').hide();
                        }

                        $('#placement_category').val(data.placement_category); // Changed from footer_position
                        $('#insert_link').val(data.insert_link);
                        $('#book_category').val(data.book_categories);
                        $('#book_level').val(data.book_levels);
                        $('#selected_author_id').val(data.associated_author_id || '0');

                        if (data.message) {
                             if (data.book_post_id === 0) {
                                alert(data.message);
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
            var selectedBookId = $('#book_post_id').val();
            var selectedPlacement = $('#placement_category').val();

            if (!selectedBookId || selectedBookId === '0') {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan',
                    text: 'Silakan pilih buku terlebih dahulu sebelum menerbitkan ke penempatan.',
                    confirmButtonText: 'OK'
                });
                return;
            }

            if (!selectedPlacement) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Peringatan',
                    text: 'Silakan pilih kategori penempatan terlebih dahulu.',
                    confirmButtonText: 'OK'
                });
                return;
            }

            Swal.fire({
                title: 'Konfirmasi Publikasi Penempatan',
                text: "Apakah Anda yakin ingin menerbitkan buku ini ke kategori penempatan '" + selectedPlacement + "'?",
                icon: 'info',
                showCancelButton: true,
                confirmButtonColor: '#3085d6',
                cancelButtonColor: '#d33',
                confirmButtonText: 'Ya, Terbitkan!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Form will submit if confirmed
                } else {
                    e.preventDefault(); // Prevent form submission if canceled
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


});