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
        var difficultyLevel = $('input[name="difficulty_level"]:checked').val() || 'regular';

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

            // Atur judul ke kolom post_title
            $('#post_title').val(finalGeneratedTitle);

            Swal.fire({
                icon: 'success',
                title: 'Successfully!',
                text: 'The generated content was successfully loaded into the main editor.',
                confirmButtonText: 'OK'
            });
        } else {
            // Fallback jika TinyMCE tidak diinisialisasi
            $('#author_content').val(finalGeneratedContent);
            $('#post_title').val(finalGeneratedTitle);

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
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Succeed!',
                                text: 'Draft saved successfully! ID Post: ' + response.data.post_id,
                                confirmButtonText: 'OK'
                            });
                            // Optionally, clear fields or update UI, or refresh the "Select Title" dropdown
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Gagal',
                                text: 'Error menyimpan draft: ' + (response.data || 'Unknown error'),
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

    // Handle "Load Content to Editor" button for existing drafts (from "Select Title" dropdown)
    // Renamed ID from #load_content_to_editor to #load_content_to_editor_from_draft
    // in my-author-editor.php to avoid conflict with #upload_final_content_to_editor
    $(document).on('click', '#load_content_to_editor_from_draft', function() {
        var selectedPostId = $('#select_title_ce').val(); // This is the post ID

        if (selectedPostId) {
            $.ajax({
                url: maeV3EditorAjax.ajaxurl, // Use the general editor AJAX URL
                type: 'POST',
                data: {
                    action: 'mae_v3_load_content', // Action to load existing post content
                    post_id: selectedPostId,
                    nonce: maeV3EditorAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        $('#post_title').val(data.post_title);
                        $('#author_subtitle').val(data.author_subtitle);
                        $('#author_photo_url').val(data.author_photo_url);
                        if (data.author_photo_url) {
                            $('#author_photo_preview').html(`<img src=\"${data.author_photo_url}\" class=\"w-full h-auto object-cover rounded-none\" />`).show();
                        } else {
                            $('#author_photo_preview').html('').hide();
                        }
                        // Update TinyMCE editor for Biography/Description
                        if (typeof tinymce !== 'undefined' && tinymce.get('author_content')) {
                            tinymce.get('author_content').setContent(data.post_content); // Using post_content from response
                        } else {
                            $('#author_content').val(data.post_content); // Fallback
                        }
                        $('#author_category').val(data.author_category).trigger('change'); // Update Select2
                        $('#author_level').val(data.author_level).trigger('change'); // Update Select2
                        $('#footer_position').val(data.footer_position);
                        $('#insert_link').val(data.insert_link);
                        
                        Swal.fire({
                            icon: 'success',
                            title: 'Succesfully!',
                            text: 'Content loaded successfully!',
                            confirmButtonText: 'OK'
                        });

                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Failed',
                            text: 'Error loading content: ' + response.data,
                            confirmButtonText: 'OK'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Failed',
                        text: 'AJAX error while loading content: ' + error,
                        confirmButtonText: 'OK'
                    });
                    console.error("AJAX error: ", xhr, status, error);
                }
            });
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Warning',
                text: 'Please select a Title to load.',
                confirmButtonText: 'OK'
            });
        }
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
    $('#select_title_ce').on('change', function() {
        if ($(this).val()) {
            $('#draft_selection_container').removeClass('hidden');
        } else {
            $('#draft_selection_container').addClass('hidden');
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