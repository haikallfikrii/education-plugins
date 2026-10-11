jQuery(document).ready(function($) {
    function formatAjaxErrorData(data) {
        if (data == null) {
            return 'Unknown error';
        }
        if (typeof data === 'string') {
            return data;
        }
        if (typeof data === 'object' && data.message) {
            return data.message;
        }
        try {
            return JSON.stringify(data);
        } catch (e) {
            return 'Unknown error';
        }
    }

    // === LLM Model Dropdown JS Control (v3) ===
    var selectedModel = 'deepseek_r1';

    $('#active-model-label').text($('#llm_model_select option:selected').text());

    $('#llm_model_select').on('change', function() {
        selectedModel = $(this).val();
        $('#active-model-label').text($(this).find('option:selected').text());
    });
    selectedModel = $('#llm_model_select').val() || 'deepseek_r1';

    function syncTaskDependentInputs() {
        $('#vocab_qty_words').prop('disabled', !$('#task_chapter_vocab').is(':checked'));
        $('#quiz_mc_qty, #quiz_short_qty').prop('disabled', !$('#task_chapter_quiz').is(':checked'));
        $('#synopsis_discussion_count').prop('disabled', !$('#task_chapter_synopsis').is(':checked'));
    }
    $(document).on('change', '#task_chapter_vocab, #task_chapter_quiz, #task_chapter_synopsis', syncTaskDependentInputs);
    syncTaskDependentInputs();

    function collectV3SelectedTasks() {
        var tasks = [];
        $('#task_author_intro:checked, #task_book_intro:checked, #task_midterm:checked, #task_final:checked, #task_class_activities:checked, #task_projects:checked').each(function() {
            var id = $(this).data('task-id');
            if (id) {
                tasks.push({ id: id });
            }
        });
        if ($('#task_chapter_vocab').is(':checked')) {
            tasks.push({
                id: 'chapter_vocabulary_list',
                qty_words: parseInt($('#vocab_qty_words').val(), 10) || 0
            });
        }
        if ($('#task_chapter_quiz').is(':checked')) {
            tasks.push({
                id: 'chapter_quiz',
                mc_qty: parseInt($('#quiz_mc_qty').val(), 10) || 0,
                short_answer_qty: parseInt($('#quiz_short_qty').val(), 10) || 0
            });
        }
        if ($('#task_chapter_synopsis').is(':checked')) {
            tasks.push({
                id: 'chapter_synopsis',
                discussion_questions_count: parseInt($('#synopsis_discussion_count').val(), 10) || 0
            });
        }
        return tasks;
    }

    // Handle "Start Automation Process" button click (v3 structured JSON payload)
    $(document).on('click', '#start_full_automation', async function(e) {
        e.preventDefault();

        var $button = $(this);
        var $statusDiv = $('#full_automation_status');

        $('.output-content-textarea').val('');
        $('#final_generated_title').val('');
        $('#final_generated_content').val('');
        $statusDiv.text('').addClass('hidden');

        // Support Author + Book contexts safely (Book page uses #book_name).
        var bookName = ($('#automation_book_name').val() || $('#book_name').val() || '').trim();
        var authorName = ($('#author_name').val() || '').trim();
        var difficultyLevel = ($('#difficulty_level_select').val() || $('input[name="difficulty_level"]:checked').val() || 'regular');
        var gradeLevel = ($('#grade_level').val() || 'middle_school');

        if (!bookName || !authorName) {
            $statusDiv.text('Please enter both Book name and Author name.').removeClass('hidden').css('color', 'red');
            return;
        }

        var selectedTasks = collectV3SelectedTasks();
        if (selectedTasks.length === 0) {
            $statusDiv.text('Please select at least one content option.').removeClass('hidden').css('color', 'red');
            return;
        }

        var automationPayload = {
            book_name: bookName,
            author_name: authorName,
            model: selectedModel,
            selected_model: selectedModel,
            task_type: 'book_introduction',
            difficulty_level: difficultyLevel,
            grade_level: gradeLevel,
            selected_tasks: selectedTasks
        };

        $button.prop('disabled', true).text('Sending…');
        $statusDiv.text('Progress: Sending JSON payload to n8n (master prompts resolved on server)…').removeClass('hidden').css('color', 'blue');

        try {
            var response = await $.ajax({
                url: maeV3EditorAIAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mae_v3_process_automation_trigger',
                    nonce: maeV3EditorAIAjax.nonce,
                    automation_payload: JSON.stringify(automationPayload)
                },
                dataType: 'json'
            });

            if (response && response.success) {
                $statusDiv.text('Progress: Complete — content mapped to Title & Content below.').removeClass('hidden').css('color', 'green');

                var payload = response.data && response.data.n8n_response ? response.data.n8n_response : null;

                if (payload && typeof payload === 'object') {
                    var finalTitle = payload.final_title || '';
                    var finalContent = payload.final_content || '';

                    $('#final_generated_title').val(finalTitle);
                    $('#final_generated_content').val(finalContent || JSON.stringify(payload, null, 2));
                } else if (payload) {
                    $('#final_generated_title').val('');
                    $('#final_generated_content').val(payload);
                } else {
                    $('#final_generated_title').val('');
                    $('#final_generated_content').val('No specific content returned from n8n, but process was successful.');
                }
            } else {
                $statusDiv.text('Automation error: ' + formatAjaxErrorData(response && response.data)).removeClass('hidden').css('color', 'red');
                console.error('Backend error:', response);
            }
        } catch (error) {
            $statusDiv.text('Process failed: ' + (error && error.message ? error.message : String(error))).removeClass('hidden').css('color', 'red');
            console.error('Automation process error:', error);
        } finally {
            $button.prop('disabled', false).text('Start Automation Process');
        }
    });

    // Load to Main Editor button (for final generated content)
    $(document).on('click', '#upload_final_content_to_editor', function(e) {
        e.preventDefault();

        // Ambil konten dari area teks output
        var finalGeneratedContent = $('#final_generated_content').val();
        var finalGeneratedTitle = $('#final_generated_title').val();

        // Pastikan ada konten yang dihasilkan sebelum mencoba memuatnya
        if (finalGeneratedContent.trim() === '') {
            Swal.fire({
                icon: 'warning',
                title: 'Warning',
                text: 'No content was generated to load.',
                confirmButtonText: 'OK'
            });
            return;
        }

        // Periksa apakah editor TinyMCE untuk "Biography/Description" (#author_content) ada
        if (typeof tinymce !== 'undefined' && tinymce.get('author_content')) {
            // Atur konten editor TinyMCE
            tinymce.get('author_content').setContent(finalGeneratedContent);

            $('#new_author_name').val(finalGeneratedTitle);

            Swal.fire({
                icon: 'success',
                title: 'Success',
                text: 'Generated content loaded into the main editor.',
                confirmButtonText: 'OK'
            });
        } else {
            $('#author_content').val(finalGeneratedContent);
            $('#new_author_name').val(finalGeneratedTitle);

            Swal.fire({
                icon: 'success',
                title: 'Successfully!',
                text: 'The generated content was successfully loaded into the main editor. (fallback).',
                confirmButtonText: 'OK'
            });
        }
    });


    // Save as New Draft button (for final generated content)
    $('#save_final_content_as_draft').on('click', function() {
        const title = $('#final_generated_title').val();
        const content = $('#final_generated_content').val();

        if (!title || !content) {
            Swal.fire({
                icon: 'warning',
                title: 'Warning',
                text: 'No content was generated to save as a draft.',
                confirmButtonText: 'OK'
            });
            return;
        }

        Swal.fire({
            title: 'Confirmation',
            text: "Are you sure you want to save this as a new draft?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, Save!',
            cancelButtonText: 'Cancel'
        }).then((result) => {
            if (result.isConfirmed) {
                // AJAX call to save the generated content as a new draft post type
                $.ajax({
                    url: maeV3EditorAIAjax.ajaxurl, // Use the specific AI AJAX URL
                    type: 'POST',
                    data: {
                        action: 'mae_v3_save_generated_content_as_draft', // New AJAX action
                        nonce: maeV3EditorAIAjax.nonce,
                        post_title: title,
                        post_content: content,
                        // post_status: 'draft', // Already defined in PHP handler
                        // post_type: 'author_profile' // Already defined in PHP handler
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            var postId = response.data.post_id;
                            var postTitle = response.data.post_title || title;
                            var postStatus = response.data.post_status || 'draft';
                            upsertAuthorDraftTitleOption(postId, postTitle, postStatus);
                            $(document).trigger('mae:drafts-changed', [postId]);
                            Swal.fire({
                                icon: 'success',
                                title: 'Success',
                                text: 'Draft saved and added to Generated Drafts. Load it from the list to review and publish.',
                                confirmButtonText: 'OK'
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Failed',
                                text: 'Error saving draft: ' + (response.data || 'Unknown error'),
                                confirmButtonText: 'OK'
                            });
                        }
                    },
                    error: function(xhr, status, error) {
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: 'AJAX error while saving draft: ' + error,
                            confirmButtonText: 'OK'
                        });
                        console.error("AJAX error saving draft: ", xhr, status, error);
                    }
                });
            }
        });
    });

    function getAuthorEditorAjaxConfig() {
        if (typeof maeV3EditorAjax !== 'undefined' && maeV3EditorAjax.ajaxurl) {
            return maeV3EditorAjax;
        }
        return null;
    }

    function isAuthorEditorPage() {
        return $('#author-editor-form').length > 0;
    }

    function refreshAuthorDraftTitleSelect(selectedId) {
        if (!isAuthorEditorPage()) {
            return $.Deferred().resolve().promise();
        }
        var ajax = getAuthorEditorAjaxConfig();
        if (!ajax) {
            return $.Deferred().reject().promise();
        }
        return $.ajax({
            url: ajax.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mae_v3_get_draft_titles',
                nonce: ajax.nonce
            }
        }).done(function(response) {
            if (!response || !response.success || !response.data || !Array.isArray(response.data.drafts)) {
                return;
            }
            var $sel = $('#mae_select_title_ce');
            if (!$sel.length) {
                return;
            }
            var current = selectedId || $sel.val();
            var titleCounts = {};
            response.data.drafts.forEach(function(draft) {
                var key = (draft.title || '').toLowerCase();
                titleCounts[key] = (titleCounts[key] || 0) + 1;
            });
            $sel.find('option:not(:first)').remove();
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
                $('#mae_draft_selection_container').removeClass('hidden');
            }
        });
    }

    function upsertAuthorDraftTitleOption(postId, title, status) {
        if (!isAuthorEditorPage()) {
            return;
        }
        var $sel = $('#mae_select_title_ce');
        var id = String(postId);
        var label = (title || 'Untitled') + ' (' + (status || 'draft') + ')';
        var $existing = $sel.find('option[value="' + id + '"]');
        if ($existing.length) {
            $existing.text(label);
        } else {
            $sel.append($('<option>', { value: id, text: label }));
        }
        $sel.val(id);
        $('#mae_draft_selection_container').removeClass('hidden');
        $('#mae_select_draft_ce').val('main');
    }

    window.maeRefreshAuthorDraftTitles = refreshAuthorDraftTitleSelect;

    function applyAuthorDataToMainEditor(data, postId) {
        if (!data) {
            return;
        }
        var resolvedId = postId || data.post_id || '';
        if (resolvedId) {
            $('#author_post_id').val(resolvedId);
            $('#selected_author_id').val(String(resolvedId));
        }
        $('#new_author_name').val(data.post_title || data.title || '');
        $('#author_subtitle').val(data.author_subtitle || '');
        $('#insert_link').val(data.insert_link || '');
        if (data.author_photo_id) {
            $('#author_photo_id').val(data.author_photo_id);
        }
        if (data.author_photo_url) {
            $('#author_photo_url').val(data.author_photo_url);
            $('#author-photo-preview').html('<img src="' + data.author_photo_url + '" class="w-full h-auto object-cover rounded-none" alt="" />').show();
        } else {
            $('#author_photo_id').val('');
            $('#author_photo_url').val('');
            $('#author-photo-preview').html('').hide();
        }
        if (typeof window.maeFillAttachPhotosFromData === 'function') {
            window.maeFillAttachPhotosFromData(data);
        } else {
            for (var n = 1; n <= 3; n++) {
                var pid = data['attach_photo_' + n + '_id'] || '';
                var purl = data['attach_photo_' + n + '_url'] || '';
                $('#attach_photo_' + n + '_id').val(pid);
                $('#attach_photo_' + n + '_url').val(purl);
                $('#attach_photo_' + n + '_link').val(data['attach_photo_' + n + '_link'] || '');
                var $preview = $('#attach-photo-' + n + '-preview');
                if (purl) {
                    $preview.html(
                        '<img src="' + purl + '" alt="Image #' + n + ' Preview" style="max-width: 150px; height: auto; display: inline-block; margin-bottom: 5px;" />' +
                        '<button type="button" class="remove-attach-photo text-red-500 hover:text-red-700 text-xs mt-1 rounded-none block mx-auto" data-attach-prefix="attach_photo" data-photo-index="' + n + '">Remove Image</button>'
                    ).show();
                } else {
                    $preview.hide().empty();
                }
            }
        }
        var body = data.post_content || data.author_body_content || data.content || '';
        if (typeof tinymce !== 'undefined' && tinymce.get('author_content')) {
            tinymce.get('author_content').setContent(body);
        } else {
            $('#author_content').val(body);
        }
        if (data.author_category) {
            $('#author_category').val(data.author_category).trigger('change');
        }
        if (data.author_level) {
            $('#author_level').val(data.author_level).trigger('change');
        }
        if (data.placement_category && typeof window.maeSetPlacements === 'function') {
            window.maeSetPlacements(data.placement_category);
        } else if (typeof window.maeSyncAttachConfigByPlacement === 'function') {
            window.maeSyncAttachConfigByPlacement();
        }
        var postStatus = data.post_status || 'draft';
        var submitAction = (window.caastWorkflow && window.caastWorkflow.statusToAction)
            ? window.caastWorkflow.statusToAction(postStatus)
            : (postStatus === 'publish' ? 'publish' : (postStatus === 'archive' ? 'archive' : 'draft'));
        $('#submit_author_action_select').val(submitAction);
    }

    // Load saved author / draft content into the main editor (mirrors lesson editor flow).
    $(document).on('click', '#load_content_to_editor_from_draft', function(e) {
        e.preventDefault();
        var ajax = getAuthorEditorAjaxConfig();
        if (!ajax) {
            console.error('maeV3EditorAjax is not defined.');
            return;
        }
        var selectedPostId = $('#mae_select_title_ce').val();
        var draftType = ($('#mae_select_draft_ce').val() || 'main').trim();

        if (!selectedPostId) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'Select a title', text: 'Choose an author from the list.' });
            }
            return;
        }

        $.ajax({
            url: ajax.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mae_v3_load_draft_content',
                post_id: selectedPostId,
                draft_type: draftType || 'main',
                nonce: ajax.nonce
            }
        }).done(function(response) {
            if (response && response.success && response.data) {
                applyAuthorDataToMainEditor(response.data, selectedPostId);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'success', title: 'Loaded', timer: 1600, showConfirmButton: false });
                }
            } else {
                var err = formatAjaxErrorData(response && response.data);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'error', title: 'Failed', text: err });
                } else {
                    alert(err);
                }
            }
        }).fail(function(xhr, status, error) {
            console.error('Load draft AJAX error:', xhr, status, error);
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Failed', text: error || 'Load failed' });
            }
        });
    });

    // Initialize Select2 for categories and levels
    // Pastikan elemen dengan kelas .select2-category dan .select2-level ada di HTML Anda
    // agar Select2 dapat diinisialisasi dengan benar.
    if ($.fn.select2) { // Periksa apakah Select2 sudah dimuat
        $('.select2-category').select2({
            placeholder: "-- Select Category --",
            allowClear: true
        });
        $('.select2-level').select2({
            placeholder: "-- Select Level --",
            allowClear: true
        });
    }

    // Existing individual "Test Agent Prompt" button logic (still present in HTML)
    // This part handles the individual agent testing, not the full automation.
    $(document).on('click', '.generate-content-button', function(e) {
        e.preventDefault();

        var $button = $(this);
        var $container = $button.closest('.my-content-automation-container');

        var aiPrompt = $container.find('.prompt-input-field').val();
        var aiModel = $container.find('.ai-model-select-field').val();
        var agentId = $container.find('.agent-id-field').val();
        var $outputContent = $container.find('.output-content-textarea');
        var $statusDiv = $container.find('.response-status-div');

        if (aiPrompt.trim() === '') {
            $statusDiv.text('Please enter a prompt for Agent ' + agentId + '.').removeClass('hidden').css('color', 'red');
            return;
        }

        $button.prop('disabled', true).text('Generating...');
        $statusDiv.text('Generating content for Agent ' + agentId + ', please wait...').removeClass('hidden').css('color', 'blue');
        $outputContent.val('');

        var formData = new FormData();
        formData.append('action', 'mae_v3_generate_ai_content'); // AJAX action for individual agent
        formData.append('nonce', maeV3EditorAIAjax.nonce);
        formData.append('prompt', aiPrompt);
        formData.append('ai_model', aiModel);
        formData.append('agent_id', agentId);
        // Book context has no upload fields; keep request file-free to avoid undefined crashes.

        $.ajax({
            url: maeV3EditorAIAjax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    var generatedContent = response.data.content;
                    $outputContent.val(generatedContent);
                    $statusDiv.text('Agent ' + agentId + ' content generated successfully!').removeClass('hidden').css('color', 'green');
                } else {
                    $statusDiv.text('Error Agent ' + agentId + ': ' + response.data).removeClass('hidden').css('color', 'red');
                }
            },
            error: function(xhr, status, error) {
                $statusDiv.text('AJAX Error Agent ' + agentId + ': ' + error + ' (Status: ' + status + ')').removeClass('hidden').css('color', 'red');
                console.error("AJAX error for Agent " + agentId + ": ", xhr, status, error);
            },
            complete: function() {
                $button.prop('disabled', false).text('Test Agent ' + agentId + ' Prompt');
            }
        });
    });

    // Logic for showing/hiding draft selection based on title selection
    $(document).on('mae:drafts-changed', function(e, selectedId) {
        refreshAuthorDraftTitleSelect(selectedId);
    });

    if (isAuthorEditorPage()) {
        refreshAuthorDraftTitleSelect();
    }

    $('#mae_select_title_ce').on('change', function() {
        if ($(this).val()) {
            $('#mae_draft_selection_container').removeClass('hidden');
        } else {
            $('#mae_draft_selection_container').addClass('hidden');
        }
    });

    // Logika untuk tombol "Generate from Last Agent" (jika masih digunakan)
    $(document).on('click', '#generate_from_last_agent', function(e) {
        e.preventDefault();
        var $button = $(this);
        var $outputContent = $('#final_generated_content');
        var $statusDiv = $('#full_automation_status'); // Use the main status div

        var previousContent = $outputContent.val();
        // You might need a way to determine the 'last_agent_model' dynamically
        // or select it from a dropdown if this button is for sequential generation.
        // For simplicity, this example assumes a hardcoded model or a selection.
        var lastAgentModel = 'qwen_2_5'; // Example: assuming Qwen 2.5 was the last agent

        if (!previousContent) {
            $statusDiv.text('No content available to generate from.').removeClass('hidden').css('color', 'red');
            return;
        }

        $button.prop('disabled', true).text('Generating from Last Agent...');
        $statusDiv.text('Processing with last agent...').removeClass('hidden').css('color', 'orange');

        var formData = new FormData();
        formData.append('action', 'mae_v3_generate_from_last_agent');
        formData.append('nonce', maeV3EditorAIAjax.nonce);
        formData.append('previous_content', previousContent);
        formData.append('last_agent_model', lastAgentModel); // Pass the model for n8n to use

        $.ajax({
            url: maeV3EditorAIAjax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    var generatedContent = response.data.content;
                    $outputContent.val(generatedContent);
                    $statusDiv.text('Content generated from last agent successfully!').removeClass('hidden').css('color', 'green');
                } else {
                    $statusDiv.text('Error generating from last agent: ' + response.data).removeClass('hidden').css('color', 'red');
                }
            },
            error: function(xhr, status, error) {
                $statusDiv.text('AJAX Error from last agent: ' + error + ' (Status: ' + status + ')').removeClass('hidden').css('color', 'red');
                console.error("AJAX error for last agent: ", xhr, status, error);
            },
            complete: function() {
                $button.prop('disabled', false).text('Generate from Last Agent');
            }
        });
    });

    // Copy generated content (UI only; does not alter automation payload or AJAX)
    $(document).on('click', '#copy_final_generated_content_btn', function(e) {
        e.preventDefault();
        var text = $('#final_generated_content').val();
        if (!text || !String(text).trim()) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'info',
                    title: 'Nothing to copy',
                    text: 'Generate content first, then copy.',
                    confirmButtonText: 'OK'
                });
            }
            return;
        }
        function notifyOk() {
            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    icon: 'success',
                    title: 'Copied to clipboard',
                    timer: 1800,
                    showConfirmButton: false
                });
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
            try {
                document.execCommand('copy');
            } catch (err) {}
            document.body.removeChild(ta);
            notifyOk();
        }
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(String(text)).then(notifyOk).catch(function() {
                fallbackCopy(String(text));
            });
        } else {
            fallbackCopy(String(text));
        }
    });

});