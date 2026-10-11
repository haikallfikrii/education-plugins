(function($) {
    // Book endpoint automation: use book_v3_data (localized) to avoid colliding with other plugins' globals.
    function bookEditorAjax() {
        if (typeof myBookEditorAjax !== 'undefined' && myBookEditorAjax && myBookEditorAjax.ajaxurl) {
            return myBookEditorAjax;
        }
        if (typeof book_v3_data !== 'undefined' && book_v3_data && book_v3_data.ajaxurl) {
            return book_v3_data;
        }
        return null;
    }

    function bookV3Ajax() {
        return bookEditorAjax();
    }

    function isBookEditorPage() {
        return $('#book-editor-form').length > 0;
    }

    function refreshBookDraftTitleSelect(selectedId) {
        if (!isBookEditorPage()) {
            return $.Deferred().resolve().promise();
        }
        var ajax = bookEditorAjax();
        if (!ajax) {
            return $.Deferred().reject().promise();
        }
        return $.ajax({
            url: ajax.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'my_book_editor_get_draft_titles',
                nonce: ajax.nonce
            }
        }).done(function(response) {
            if (!response || !response.success || !response.data || !Array.isArray(response.data.drafts)) {
                return;
            }
            var $sel = $('#mbe_select_title_ce');
            if (!$sel.length) {
                return;
            }
            var current = selectedId || $sel.val();
            $sel.find('option:not(:first)').remove();
            var titleCounts = {};
            response.data.drafts.forEach(function(draft) {
                var key = (draft.title || '').toLowerCase();
                titleCounts[key] = (titleCounts[key] || 0) + 1;
            });
            response.data.drafts.forEach(function(draft) {
                var label = (draft.title || 'Untitled') + ' (' + ((window.caastWorkflow && window.caastWorkflow.formatStatusLabel) ? window.caastWorkflow.formatStatusLabel(draft.status || 'draft') : (draft.status || 'draft')) + ')';
                if (titleCounts[(draft.title || '').toLowerCase()] > 1) {
                    label += ' #' + draft.id;
                }
                $sel.append($('<option>', { value: String(draft.id), text: label }));
            });
            if (current) {
                $sel.val(String(current));
            }
            if ($sel.val()) {
                $('#mbe_draft_selection_container').removeClass('hidden');
            }
        });
    }

    function upsertBookDraftTitleOption(postId, title, status) {
        if (!isBookEditorPage()) {
            return;
        }
        var $sel = $('#mbe_select_title_ce');
        var id = String(postId);
        var label = title + ' (' + ((window.caastWorkflow && window.caastWorkflow.formatStatusLabel) ? window.caastWorkflow.formatStatusLabel(status || 'draft') : (status || 'draft')) + ')';
        var $existing = $sel.find('option[value="' + id + '"]');
        if ($existing.length) {
            $existing.text(label);
        } else {
            $sel.append($('<option>', { value: id, text: label }));
        }
        $sel.val(id);
        $('#mbe_draft_selection_container').removeClass('hidden');
        $('#mbe_select_draft_ce').val('main');
    }

    window.mbeRefreshBookDraftTitles = refreshBookDraftTitleSelect;

    function bookEditorFormatAjaxErrorData(data) {
        if (data == null) {
            return 'Unknown error';
        }
        if (typeof data === 'string') {
            return data;
        }
        if (typeof data === 'object' && data.message) {
            return String(data.message);
        }
        try {
            return JSON.stringify(data);
        } catch (e) {
            return String(data);
        }
    }

    // AI model label (same UX as my-author-editor v3).
    $('#active-model-label').text($('#llm_model_select option:selected').text());
    $(document).on('change', '#llm_model_select', function() {
        $('#active-model-label').text($(this).find('option:selected').text());
    });

    function bookEditorExtractN8nFields(raw) {
        if (raw == null) {
            return { title: '', content: '' };
        }
        if (typeof raw === 'string') {
            return { title: '', content: String(raw) };
        }
        var obj = raw;
        if (obj && typeof obj === 'object' && obj.data && typeof obj.data === 'object' && !Array.isArray(obj.data)) {
            obj = obj.data;
        }
        var title = '';
        var content = '';
        if (typeof obj === 'object' && obj !== null) {
            title = obj.final_title || obj.title || obj.book_title || '';
            if (obj.final_content != null && obj.final_content !== '') {
                content = obj.final_content;
            } else if (obj.content != null && obj.content !== '') {
                content = obj.content;
            } else if (obj.body != null && obj.body !== '') {
                content = obj.body;
            } else if (obj.output != null && obj.output !== '') {
                content = obj.output;
            }
        }
        if (typeof content !== 'string') {
            content = content ? JSON.stringify(content, null, 2) : '';
        }
        return { title: String(title), content: String(content) };
    }

    function getInputValueById(id) {
        var el = document.getElementById(id);
        return el && typeof el.value !== 'undefined' ? String(el.value) : '';
    }

    // Copied Author-style async AJAX flow, adapted for Book selectors and task type.
    $(document).on('click', '.generate-btn, #start_full_automation', async function(e) {
        e.preventDefault();
        console.log('Book Automation started...');

        var $button = $(this);
        var v3 = bookV3Ajax();
        var $statusDiv = $('#full_automation_status');
        var $label = $button.find('.mbe-generate-btn-label');
        var defaultLabel = $button.data('defaultLabel');
        if (!defaultLabel) {
            defaultLabel = $label.length ? $label.text() : $button.text().replace(/\s+/g, ' ').trim() || 'Generate';
            $button.data('defaultLabel', defaultLabel);
        }

        $('.output-content-textarea').val('');
        $('#final_generated_title').val('');
        $('#final_generated_content').val('');
        $statusDiv.text('').addClass('hidden');

        var bookName = getInputValueById('book_name').trim();
        var selectedModel = getInputValueById('llm_model_select') || 'deepseek_r1';
        var difficultyLevel = ($('#difficulty_level_select').val() || $('input[name="difficulty_level"]:checked').val() || 'regular');
        var gradeLevel = ($('#grade_level').val() || 'middle_school');

        if (!bookName) {
            $statusDiv.text('Please enter book name.').removeClass('hidden').css('color', 'red');
            return;
        }

        var automationPayload = {
            book_name: bookName,
            model: selectedModel,
            task_type: 'book_introduction',
            difficulty_level: difficultyLevel,
            grade_level: gradeLevel
        };

        $button.prop('disabled', true).addClass('mbe-generate-btn--busy');
        if ($label.length) {
            $label.text('Generating...');
        } else {
            $button.text('Generating...');
        }
        $statusDiv.text('Progress: Sending JSON payload to n8n (book introduction)...').removeClass('hidden').css('color', 'blue');

        try {
            var response = await $.ajax({
                url: v3.ajaxurl,
                type: 'POST',
                data: {
                    action: 'my_book_editor_full_automation',
                    nonce: v3.nonce,
                    automation_payload: JSON.stringify(automationPayload)
                },
                dataType: 'json'
            });

            if (response && response.success) {
                $statusDiv.text('Progress: Complete — content mapped to Title & Content below.').removeClass('hidden').css('color', 'green');
                var d = response.data || {};
                var payload = d.n8n_response != null ? d.n8n_response : null;
                var title = (d.final_title || '').trim();
                var content = (d.final_content != null && d.final_content !== '') ? String(d.final_content) : '';
                var mapped = bookEditorExtractN8nFields(payload);

                if (!title) {
                    title = mapped.title;
                }
                if (!content) {
                    content = mapped.content;
                }
                if (!title && bookName) {
                    title = bookName;
                }
                if (!content && payload) {
                    if (typeof payload === 'string') {
                        content = payload;
                    } else if (typeof payload === 'object') {
                        content = JSON.stringify(payload, null, 2);
                    }
                }

                $('#final_generated_title').val(title || '');
                $('#final_generated_content').val(content || '');
            } else {
                $statusDiv.text('Automation error: ' + bookEditorFormatAjaxErrorData(response && response.data)).removeClass('hidden').css('color', 'red');
                console.error('Book automation backend error:', response);
            }
        } catch (error) {
            $statusDiv.text('Process failed: ' + (error && error.message ? error.message : String(error))).removeClass('hidden').css('color', 'red');
            console.error('Book automation process error:', error);
        } finally {
            $button.prop('disabled', false).removeClass('mbe-generate-btn--busy');
            if ($label.length) {
                $label.text(defaultLabel);
            } else {
                $button.text(defaultLabel);
            }
        }
    });

    $(document).on('click', '#copy_final_generated_content_btn', function(e) {
        e.preventDefault();
        var text = $('#final_generated_content').val();
        if (!text || !String(text).trim()) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'info', title: 'Nothing to copy', text: 'Generate content first.', confirmButtonText: 'OK' });
            }
            return;
        }
        function ok() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'success', title: 'Copied', timer: 1500, showConfirmButton: false });
            }
        }
        function fallbackCopy(t) {
            var ta = document.createElement('textarea');
            ta.value = t;
            ta.setAttribute('readonly', '');
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); } catch (err2) {}
            document.body.removeChild(ta);
            ok();
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(String(text)).then(ok).catch(function() { fallbackCopy(String(text)); });
        } else {
            fallbackCopy(String(text));
        }
    });

    // --- Fungsionalitas Mengunggah Konten ke Editor Utama (Automation Section) ---
    $(document).on('click', '#upload_final_content_to_editor', function(e) {
        e.preventDefault();

        var finalTitle = $('#final_generated_title').val();
        var finalContent = $('#final_generated_content').val();

        if (finalContent) {
            if (finalTitle) {
                $('#new_book_name').val(finalTitle);
            }

            if (typeof tinymce !== 'undefined' && tinymce.get('book_content')) {
                tinymce.get('book_content').setContent(finalContent);
            } else {
                $('#book_content').val(finalContent);
            }

            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'Content loaded into the main editor.',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Warning',
                text: 'No generated content to load. Run automation first.',
                confirmButtonText: 'OK'
            });
        }
    });

    // --- Fungsionalitas Menyimpan Konten sebagai Draf Baru (Automation Section) ---
    $('#save_final_content_as_draft').on('click', function(e) {
        e.preventDefault();

        var ajax = bookEditorAjax();
        if (!ajax) {
            console.error('Book editor AJAX config is missing.');
            return;
        }

        var title = ($('#final_generated_title').val() || '').trim();
        var content = ($('#final_generated_content').val() || '').trim();

        if (!title || !content) {
            Swal.fire({
                icon: 'error',
                title: 'Failed',
                text: 'Generated content is empty. Please run automation first.',
                confirmButtonText: 'OK'
            });
            return;
        }

        var formData = new FormData();
        formData.append('action', 'my_book_editor_save_final_content_as_draft');
        formData.append('nonce', ajax.nonce);
        formData.append('title', title);
        formData.append('content', content);

        $.ajax({
            url: ajax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function(response) {
            if (response && response.success && response.data) {
                var postId = response.data.post_id;
                var postTitle = response.data.post_title || title;
                var postStatus = response.data.post_status || 'draft';
                upsertBookDraftTitleOption(postId, postTitle, postStatus);
                $(document).trigger('mbe:drafts-changed', [postId]);
                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'Draft saved and added to Generated Drafts Content. Load it from the main editor to review and publish.',
                    confirmButtonText: 'OK'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Failed',
                    text: 'Error: ' + bookEditorFormatAjaxErrorData(response && response.data),
                    confirmButtonText: 'OK'
                });
            }
        }).fail(function() {
            Swal.fire({
                icon: 'error',
                title: 'Failed',
                text: 'Could not connect to the server. Please try again.',
                confirmButtonText: 'OK'
            });
        });
    });

    // Load saved draft content into the main editor.
    $('#load_content_to_editor_from_draft').on('click', function(e) {
        e.preventDefault();

        var ajax = bookEditorAjax();
        if (!ajax) {
            console.error('Book editor AJAX config is missing.');
            return;
        }

        var postId = $('#mbe_select_title_ce').val();
        var draftType = ($('#mbe_select_draft_ce').val() || 'main').trim();

        if (!postId) {
            Swal.fire({
                icon: 'error',
                title: 'Failed',
                text: 'Please select a title first.',
                confirmButtonText: 'OK'
            });
            return;
        }

        $.ajax({
            url: ajax.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'my_book_editor_load_draft_content',
                nonce: ajax.nonce,
                post_id: postId,
                draft_type: draftType || 'main'
            }
        }).done(function(response) {
            if (response && response.success && response.data) {
                window.myBookEditorPopulateForm(response.data);

                Swal.fire({
                    icon: 'success',
                    title: 'Success',
                    text: 'Draft loaded into the main editor.',
                    confirmButtonText: 'OK'
                });
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Failed',
                    text: 'Error: ' + bookEditorFormatAjaxErrorData(response && response.data),
                    confirmButtonText: 'OK'
                });
            }
        }).fail(function() {
            Swal.fire({
                icon: 'error',
                title: 'Failed',
                text: 'Could not load draft content. Please try again.',
                confirmButtonText: 'OK'
            });
        });
    });

    // Show/hide draft type dropdown when a title is selected.
    $('#mbe_select_title_ce').on('change', function() {
        if ($(this).val()) {
            $('#mbe_draft_selection_container').removeClass('hidden');
            if (!$('#mbe_select_draft_ce').val()) {
                $('#mbe_select_draft_ce').val('main');
            }
        } else {
            $('#mbe_draft_selection_container').addClass('hidden');
        }
    });

    $(document).on('mbe:drafts-changed', function(e, selectedId) {
        refreshBookDraftTitleSelect(selectedId);
    });

    if (isBookEditorPage()) {
        refreshBookDraftTitleSelect();
    }
})(jQuery);
