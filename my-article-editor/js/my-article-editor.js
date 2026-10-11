jQuery(document).ready(function($) {
    var mediaUploader;

    $('#upload-featured-image-button').on('click', function(e) {
        e.preventDefault();
        // If the uploader object has already been created, reopen the dialog
        if (mediaUploader) {
            mediaUploader.open();
            return;
        }
        // Extend the wp.media object
        mediaUploader = wp.media.frames.file_frame = wp.media({
            title: 'Choose Featured Image',
            button: {
                text: 'Choose Image'
            },
            multiple: false // Set to true to allow multiple files to be selected
        });

        // When a file is selected, grab the URL and set it as the text field's value
        mediaUploader.on('select', function() {
            var attachment = mediaUploader.state().get('selection').first().toJSON();
            $('#featured-image-id').val(attachment.id);
            $('#featured-image-preview').attr('src', attachment.url).removeClass('hidden');
            $('#remove-featured-image-button').removeClass('hidden');
        });

        // Open the uploader dialog
        mediaUploader.open();
    });

    $('#remove-featured-image-button').on('click', function(e) {
        e.preventDefault();
        $('#featured-image-id').val('');
        $('#featured-image-preview').attr('src', '').addClass('hidden');
        $(this).addClass('hidden');
    });

    // Handle initial state of remove button on page load
    if ($('#featured-image-id').val() === '') {
        $('#remove-featured-image-button').addClass('hidden');
    }

    function decodeEntities(value) {
        return $('<textarea>').html(value || '').text();
    }

    function setArticlePlacements(placements) {
        if (!Array.isArray(placements)) {
            placements = placements ? [placements] : [];
        }
        $('.caast-placement-checkbox').each(function() {
            $(this).prop('checked', placements.indexOf($(this).val()) !== -1);
        });
    }

    function populateArticleForm(data) {
        $('#article_post_id').val(data.article_post_id || 0);
        $('#new_article_name').val(decodeEntities(data.article_name));
        $('#article_subtitle').val(decodeEntities(data.article_subtitle));

        var body = data.article_content || '';
        if (typeof tinymce !== 'undefined' && tinymce.get('article_content')) {
            tinymce.get('article_content').setContent(body);
        } else {
            $('#article_content').val(body);
        }

        $('#article_cover_id').val(data.article_cover_id || '');
        $('#article_cover_url').val(data.article_cover_url || '');
        if (data.article_cover_url) {
            $('#article-cover-preview img').attr('src', data.article_cover_url);
            $('#article-cover-preview').show();
        } else {
            $('#article-cover-preview').hide();
        }

        $('#article_category').val(data.article_categories || '');
        $('#article_level').val(data.article_levels || '');
        if (data.associated_author_id) {
            $('#selected_author_id').val(String(data.associated_author_id));
        }
        setArticlePlacements(data.placement_category);
    }

    $('#load-article-content-btn').on('click', function(e) {
        e.preventDefault();
        if (typeof myArticleEditorAjax === 'undefined') {
            return;
        }
        var articleId = $('#selected_article_id').val();
        if (!articleId) {
            alert(myArticleEditorAjax.alert_no_article_selected);
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Loading...');
        $.post(myArticleEditorAjax.ajaxurl, {
            action: 'my_article_editor_get_article_content',
            article_id: articleId,
            nonce: myArticleEditorAjax.nonce
        }).done(function(response) {
            if (response && response.success) {
                populateArticleForm(response.data || {});
                if (response.data && response.data.message) {
                    alert(response.data.message);
                }
            } else {
                alert(myArticleEditorAjax.alert_content_not_found);
            }
        }).fail(function() {
            alert(myArticleEditorAjax.alert_content_not_found);
        }).always(function() {
            $btn.prop('disabled', false).text('Submit');
        });
    });

    $('form').has('.caast-placement-checkbox').on('submit', function(e) {
        var submitter = e.originalEvent && e.originalEvent.submitter ? e.originalEvent.submitter : null;
        if (!submitter || submitter.name !== 'placement_action' || submitter.value !== 'publish_placement') {
            return;
        }

        var articlePostId = $('#article_post_id').val();
        if (!articlePostId || articlePostId === '0') {
            e.preventDefault();
            alert('Please select and load an article before publishing to a placement.');
            return;
        }

        var $checked = $('.caast-placement-checkbox:checked');
        if (!$checked.length) {
            e.preventDefault();
            alert('Please tick at least one placement.');
            return;
        }

        var labels = $checked.map(function() {
            return $.trim($(this).closest('label').text());
        }).get().join(', ');
        if (!window.confirm('Publish this article to: ' + labels + '?')) {
            e.preventDefault();
        }
    });
});