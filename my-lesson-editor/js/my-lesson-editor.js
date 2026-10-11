/**
 * My Lesson Editor — shortcode [my_lesson_editor] (lesson_profile + AI panel).
 */
jQuery(document).ready(function($) {
    'use strict';

    var mle = window.mylessonEditorAjax || window.myLessonPlanEditorAjax;
    if (!mle || !mle.ajaxurl || !mle.nonce) {
        return;
    }

    var lessonCoverUploader;

    function formatAjaxError(data) {
        if (data == null) {
            return 'Unknown error';
        }
        if (typeof data === 'string') {
            return data;
        }
        if (data.message) {
            return data.message;
        }
        try {
            return JSON.stringify(data);
        } catch (e) {
            return String(data);
        }
    }

    function getSelectedAuthorName() {
        var $opt = $('#selected_author_id option:selected');
        if (!$opt.length || !$opt.val()) {
            return '';
        }
        return ($opt.text() || '').trim();
    }

    function collectTasksForN8n() {
        var tasks = {
            lesson_plan: false,
            vocabulary: false,
            flashcards: false,
            quiz: false,
            midterm: false,
            final_exam: false
        };
        $('.content-type-checkbox:checked').each(function() {
            var key = $(this).data('task-key') || $(this).val();
            if (key === 'final') {
                key = 'final_exam';
            }
            if (Object.prototype.hasOwnProperty.call(tasks, key)) {
                tasks[key] = true;
            }
        });
        return tasks;
    }

    var typeLabels = {
        lesson_plan: 'Lesson plan',
        vocabulary: 'Vocabulary',
        flashcards: 'Flashcards',
        quiz: 'Quiz',
        midterm: 'Midterm',
        final_exam: 'Final exam'
    };

    function refreshContentTypeSummary() {
        var selected = [];
        $('.content-type-checkbox:checked').each(function() {
            var key = $(this).data('task-key') || $(this).val();
            if (key === 'final') {
                key = 'final_exam';
            }
            selected.push(typeLabels[key] || key);
        });
        $('#selected-count').text(selected.length);
        if (!selected.length) {
            $('#selected-types').html('<span class="caast-meta italic">None selected</span>');
        } else {
            $('#selected-types').html(
                '<div class="flex flex-wrap gap-1.5">' +
                selected.map(function(l) {
                    return '<span class="inline-block bg-blue-50 border border-blue-200 text-blue-800 px-2 py-0.5 rounded-full text-xs font-medium">' + $('<div/>').text(l).html() + '</span>';
                }).join('') +
                '</div>'
            );
        }
    }

    $(document).on('change', '.content-type-checkbox', refreshContentTypeSummary);
    refreshContentTypeSummary();

    /* --- Lesson cover (wp.media) --- */
    $(document).on('click', '.browse-lesson-cover', function(e) {
        e.preventDefault();
        if (typeof wp === 'undefined' || !wp.media) {
            return;
        }
        if (lessonCoverUploader) {
            lessonCoverUploader.open();
            return;
        }
        lessonCoverUploader = wp.media({
            title: 'Choose lesson cover',
            button: { text: 'Select cover' },
            multiple: false
        });
        lessonCoverUploader.on('select', function() {
            var attachment = lessonCoverUploader.state().get('selection').first().toJSON();
            $('#lesson_cover_id').val(attachment.id);
            $('#lesson_cover_url').val(attachment.url);
            $('#lesson-cover-preview img').attr('src', attachment.url);
            $('#lesson-cover-preview').show();
        });
        lessonCoverUploader.open();
    });

    $(document).on('click', '.remove-lesson-cover', function(e) {
        e.preventDefault();
        $('#lesson_cover_id').val('');
        $('#lesson_cover_url').val('');
        $('#lesson-cover-preview img').attr('src', '');
        $('#lesson-cover-preview').hide();
    });

    /* --- Load lesson --- */
    $('#load-lesson-content-btn').on('click', function(e) {
        e.preventDefault();
        var lessonId = $('#selected_lesson_id').val();
        var $btn = $(this);
        if (!lessonId) {
            alert(mle.alert_no_lesson_selected || 'Select a lesson first.');
            return;
        }
        $.ajax({
            url: mle.ajaxurl,
            type: 'POST',
            data: {
                action: 'my_lesson_editor_get_lesson_content',
                lesson_id: lessonId,
                nonce: mle.nonce
            },
            beforeSend: function() {
                $btn.prop('disabled', true).text('Loading…');
            },
            success: function(response) {
                if (!response || !response.success || !response.data) {
                    alert(formatAjaxError(response && response.data));
                    return;
                }
                var d = response.data;
                $('#lesson_post_id').val(d.lesson_post_id || '');
                $('#new_lesson_name').val(d.lesson_name || '');
                $('#lesson_subtitle').val(d.lesson_subtitle || '');
                var placements = Array.isArray(d.placement_category)
                    ? d.placement_category
                    : (d.placement_category ? [d.placement_category] : []);
                $('.caast-placement-checkbox').each(function() {
                    $(this).prop('checked', placements.indexOf($(this).val()) !== -1);
                });
                $('#lesson_category').val(d.lesson_categories || '');
                $('#lesson_level').val(d.lesson_levels || '');
                $('#selected_author_id').val(d.associated_author_id ? String(d.associated_author_id) : '');
                if (d.lesson_cover_id && d.lesson_cover_url) {
                    $('#lesson_cover_id').val(d.lesson_cover_id);
                    $('#lesson_cover_url').val(d.lesson_cover_url);
                    $('#lesson-cover-preview img').attr('src', d.lesson_cover_url);
                    $('#lesson-cover-preview').show();
                } else {
                    $('#lesson_cover_id').val('');
                    $('#lesson_cover_url').val('');
                    $('#lesson-cover-preview').hide();
                }
                var html = d.lesson_content || '';
                if (typeof tinymce !== 'undefined' && tinymce.get('lesson_content')) {
                    tinymce.get('lesson_content').setContent(html);
                } else {
                    $('#lesson_content').val(html);
                }
                if (d.message && (d.lesson_post_id === 0 || d.lesson_post_id === '0')) {
                    alert(d.message);
                }
            },
            error: function(xhr) {
                alert('AJAX error: ' + (xhr.statusText || 'request failed'));
            },
            complete: function() {
                $btn.prop('disabled', false).text('Submit');
            }
        });
    });

    /* --- Load author (fills AI author context via book_title hint optional) --- */
    $('#load-author-content-btn').on('click', function(e) {
        e.preventDefault();
        var authorId = $('#selected_author_id').val();
        var $btn = $(this);
        if (!authorId) {
            alert(mle.alert_no_author_selected || 'Select an author first.');
            return;
        }
        $.ajax({
            url: mle.ajaxurl,
            type: 'POST',
            data: {
                action: 'my_lesson_editor_get_author_content',
                author_id: authorId,
                nonce: mle.nonce
            },
            beforeSend: function() {
                $btn.prop('disabled', true).text('Loading…');
            },
            success: function(response) {
                if (response && response.success && response.data && response.data.author_name) {
                    var name = response.data.author_name;
                    if (!$('#book_title').val()) {
                        $('#book_title').attr('placeholder', name);
                    }
                } else if (response && !response.success) {
                    alert(formatAjaxError(response.data));
                }
            },
            complete: function() {
                $btn.prop('disabled', false).text('Submit');
            }
        });
    });

    /* --- n8n automation: no CSV / no LLM prompt required --- */
    $('#start_full_automation').on('click', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var $label = $btn.find('.mle-generate-label');
        var defaultText = $btn.data('defaultLabel');
        if (!defaultText) {
            defaultText = $label.length ? $label.text() : $btn.text();
            $btn.data('defaultLabel', defaultText);
        }

        var tasks = collectTasksForN8n();
        var hasTask = Object.keys(tasks).some(function(k) {
            return tasks[k];
        });
        if (!hasTask) {
            $('#full_automation_status').removeClass('bg-green-100 text-green-800 bg-red-100 text-red-800').addClass('bg-amber-50 text-amber-900 border border-amber-200')
                .text('Select at least one content type above.');
            return;
        }

        var bookName = ($('#book_title').val() || '').trim() || ($('#new_lesson_name').val() || '').trim();
        var authorName = getSelectedAuthorName();
        var chapter = ($('#ai_chapter').val() || '').trim();
        var model = ($('#llm_model_select').val() || 'deepseek_r1').trim();
        var lessonId = parseInt($('#lesson_post_id').val(), 10) || 0;
        var lessonTitle = ($('#new_lesson_name').val() || '').trim();
        var lessonSubtitle = ($('#lesson_subtitle').val() || '').trim();

        var difficulty = ($('#ai_difficulty_level').val() || $('#lesson_level').val() || 'regular').trim();
        if (!difficulty) {
            difficulty = 'regular';
        }
        var gradeLevel = ($('#grade_level').val() || 'middle_school').trim();
        var lessonCategory = ($('#lesson_category').val() || '').trim();

        var payload = {
            book_name: bookName || lessonTitle || 'Lesson',
            author_name: authorName,
            chapter: chapter || '—',
            model: model,
            tasks: tasks,
            lesson_id: lessonId,
            lesson_title: lessonTitle,
            lesson_subtitle: lessonSubtitle,
            difficulty_level: difficulty,
            grade_level: gradeLevel,
            lesson_category: lessonCategory,
            task_quantities: {
                quiz: { mc_qty: 10, short_answer_qty: 4, discussion_questions_count: 0 },
                midterm: { mc_qty: 15, short_answer_qty: 5, discussion_questions_count: 3 },
                final_exam: { mc_qty: 20, short_answer_qty: 8, discussion_questions_count: 5 }
            }
        };

        var formData = new FormData();
        formData.append('action', 'my_lesson_editor_full_automation');
        formData.append('nonce', mle.nonce);
        formData.append('automation_payload', JSON.stringify(payload));

        try {
            var fileEl = document.getElementById('book_upload');
            if (fileEl && fileEl.files && fileEl.files.length > 0) {
                formData.append('book_upload', fileEl.files[0]);
            }
        } catch (err) {
            console.warn('book_upload skipped', err);
        }

        $('#full_automation_status').removeClass('bg-green-100 text-green-800 bg-red-100 text-red-800 bg-amber-50 text-amber-900').addClass('bg-gray-100 text-gray-800 border border-gray-200').text('Sending to n8n…');
        $btn.prop('disabled', true).addClass('opacity-60');
        if ($label.length) {
            $label.text('Running…');
        } else {
            $btn.text('Running…');
        }

        $.ajax({
            url: mle.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function(response) {
            if (response && response.success && response.data) {
                var d = response.data;
                var n8n = d.n8n_response;
                var title = (d.final_title || '').trim();
                var content = (d.final_content != null && d.final_content !== '') ? String(d.final_content) : '';
                if (!title && n8n && typeof n8n === 'object') {
                    title = n8n.final_title || n8n.title || n8n.lesson_title || n8n.book_title
                        || (n8n.data && (n8n.data.final_title || n8n.data.title || n8n.data.lesson_title)) || '';
                }
                if (!content && n8n) {
                    if (typeof n8n === 'string') {
                        content = n8n;
                    } else if (typeof n8n === 'object') {
                        content = n8n.final_content || n8n.content || n8n.lesson_content || n8n.text || n8n.result
                            || (n8n.data && (n8n.data.final_content || n8n.data.content || n8n.data.lesson_content)) || '';
                        if (!content && n8n.output && typeof n8n.output === 'string') {
                            try {
                                var parsed = JSON.parse(n8n.output);
                                content = parsed.final_content || parsed.content || parsed.lesson_content || '';
                                if (!title && (parsed.final_title || parsed.title || parsed.lesson_title)) {
                                    title = parsed.final_title || parsed.title || parsed.lesson_title;
                                }
                            } catch (ignore) {
                                content = n8n.output;
                            }
                        }
                        if (!content && Array.isArray(n8n) && n8n[0] && typeof n8n[0] === 'object') {
                            content = n8n[0].final_content || n8n[0].content || '';
                            if (!title) {
                                title = n8n[0].final_title || n8n[0].title || '';
                            }
                        }
                        if (!content) {
                            content = JSON.stringify(n8n, null, 2);
                        }
                    }
                }
                $('#final_generated_title').val(title || payload.book_name || payload.lesson_title || '');
                $('#final_generated_content').val(content || '');
                $('#full_automation_status').removeClass('hidden');
                $('#full_automation_status').removeClass('bg-gray-100 text-gray-800 bg-red-100 text-red-800').addClass('bg-green-50 text-green-900 border border-green-200').text(d.message || 'Automation finished.');
            } else {
                $('#full_automation_status').removeClass('bg-gray-100 bg-green-50 text-green-900').addClass('bg-red-50 text-red-900 border border-red-200').text(formatAjaxError(response && response.data));
            }
        }).fail(function(xhr) {
            var msg = 'Request failed';
            if (xhr.responseJSON && xhr.responseJSON.data) {
                msg = formatAjaxError(xhr.responseJSON.data);
            } else if (xhr.responseText) {
                msg = xhr.responseText.substring(0, 400);
            }
            $('#full_automation_status').removeClass('bg-gray-100 bg-green-50').addClass('bg-red-50 text-red-900 border border-red-200').text(msg);
        }).always(function() {
            $btn.prop('disabled', false).removeClass('opacity-60');
            if ($label.length) {
                $label.text(defaultText);
            } else {
                $btn.text(defaultText);
            }
        });
    });

    $('#upload_final_content_to_editor').on('click', function(e) {
        e.preventDefault();
        var title = ($('#final_generated_title').val() || '').trim();
        var content = ($('#final_generated_content').val() || '').trim();
        if (!content) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'No content', text: 'Run automation first or paste output.' });
            } else {
                alert('No generated content to load.');
            }
            return;
        }
        if (title) {
            $('#new_lesson_name').val(title);
        }
        if (typeof tinymce !== 'undefined' && tinymce.get('lesson_content')) {
            tinymce.get('lesson_content').setContent(content);
        } else {
            $('#lesson_content').val(content);
        }
        if (typeof Swal !== 'undefined') {
            Swal.fire({ icon: 'success', title: 'Loaded', timer: 1600, showConfirmButton: false });
        }
    });

    function isLessonEditorPage() {
        return $('#lesson-editor-form').length > 0;
    }

    function refreshLessonDraftTitleSelect(selectedId) {
        if (!isLessonEditorPage()) {
            return $.Deferred().resolve().promise();
        }
        return $.ajax({
            url: mle.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'my_lesson_editor_get_draft_titles',
                nonce: mle.nonce
            }
        }).done(function(response) {
            if (!response || !response.success || !response.data || !Array.isArray(response.data.drafts)) {
                return;
            }
            var $sel = $('#mle_select_title_ce');
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
                var label = (draft.title || 'Untitled') + ' (' + (draft.status || 'draft') + ')';
                if (titleCounts[(draft.title || '').toLowerCase()] > 1) {
                    label += ' #' + draft.id;
                }
                $sel.append($('<option>', { value: String(draft.id), text: label }));
            });
            if (current) {
                $sel.val(String(current));
            }
            if ($sel.val()) {
                $('#mle_draft_selection_container').removeClass('hidden');
            }
        });
    }

    function upsertLessonDraftTitleOption(postId, title, status) {
        if (!isLessonEditorPage()) {
            return;
        }
        var $sel = $('#mle_select_title_ce');
        var id = String(postId);
        var label = (title || 'Untitled') + ' (' + (status || 'draft') + ')';
        var $existing = $sel.find('option[value="' + id + '"]');
        if ($existing.length) {
            $existing.text(label);
        } else {
            $sel.append($('<option>', { value: id, text: label }));
        }
        $sel.val(id);
        $('#mle_draft_selection_container').removeClass('hidden');
        $('#mle_select_draft_ce').val('main');
    }

    window.mleRefreshDraftTitles = refreshLessonDraftTitleSelect;

    $('#save_final_content_as_draft').on('click', function(e) {
        e.preventDefault();
        var title = ($('#final_generated_title').val() || '').trim();
        var content = ($('#final_generated_content').val() || '').trim();
        if (!title || !content) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Missing', text: 'Title and content are required.' });
            } else {
                alert('Title and content required.');
            }
            return;
        }
        var fd = new FormData();
        fd.append('action', 'my_lesson_editor_save_final_content_as_draft');
        fd.append('nonce', mle.nonce);
        fd.append('title', title);
        fd.append('content', content);
        $.ajax({
            url: mle.ajaxurl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false,
            dataType: 'json'
        }).done(function(res) {
            if (res && res.success && res.data) {
                var postId = res.data.post_id;
                var postTitle = res.data.post_title || title;
                var postStatus = res.data.post_status || 'draft';
                upsertLessonDraftTitleOption(postId, postTitle, postStatus);
                if (window.mleRefreshLessonTree) {
                    window.mleRefreshLessonTree(postId);
                }
                $(document).trigger('mle:drafts-changed', [postId]);
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'success', title: 'Draft saved', text: res.data.message || 'Draft added to Generated Drafts.' });
                } else {
                    alert('Saved');
                }
            } else {
                alert(formatAjaxError(res && res.data));
            }
        }).fail(function() {
            alert('Save failed');
        });
    });

    $('#load_content_to_editor_from_draft').on('click', function(e) {
        e.preventDefault();
        var postId = $('#mle_select_title_ce').val();
        var draftType = ($('#mle_select_draft_ce').val() || '').trim();
        if (!postId) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'error', title: 'Select a title', text: 'Choose a lesson from the list.' });
            } else {
                alert('Select a title first.');
            }
            return;
        }
        $.ajax({
            url: mle.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'my_lesson_editor_load_draft_content',
                nonce: mle.nonce,
                post_id: postId,
                draft_type: draftType
            }
        }).done(function(response) {
            if (response && response.success && response.data) {
                var t = response.data.title || '';
                var c = response.data.content || '';
                if (t) {
                    $('#new_lesson_name').val(t);
                }
                if (c) {
                    if (typeof tinymce !== 'undefined' && tinymce.get('lesson_content')) {
                        tinymce.get('lesson_content').setContent(c);
                    } else {
                        $('#lesson_content').val(c);
                    }
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'success', title: 'Loaded', timer: 1600, showConfirmButton: false });
                }
            } else {
                alert(formatAjaxError(response && response.data));
            }
        }).fail(function() {
            alert('Load failed');
        });
    });

    $('#mle_select_title_ce').on('change', function() {
        if ($(this).val()) {
            $('#mle_draft_selection_container').removeClass('hidden');
        } else {
            $('#mle_draft_selection_container').addClass('hidden');
        }
    });

    $(document).on('mle:drafts-changed', function(e, selectedId) {
        refreshLessonDraftTitleSelect(selectedId);
    });

    if (isLessonEditorPage()) {
        refreshLessonDraftTitleSelect();
    }

    /* Optional: show filename for book upload */
    $('#book_upload').on('change', function() {
        var el = this;
        var name = '';
        try {
            if (el && el.files && el.files.length) {
                name = el.files[0].name;
            }
        } catch (err) {
            name = '';
        }
        $('#book_upload_status').text(name ? ('Selected: ' + name) : '');
    });

    /* --- AcademyLM block: course list + reusable Upload/Edit/Submit --- */
    function loadAcademyLMCourses(forceRefresh) {
        var $select = $('#select_academylm_course');
        if (!$select.length) {
            return;
        }
        var current = $select.val();
        $select.prop('disabled', true).empty().append($('<option/>').val('').text('Loading courses…'));
        $.ajax({
            url: mle.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mle_get_courses',
                nonce: mle.nonce,
                _force: forceRefresh ? 1 : 0
            }
        }).done(function(res) {
            $select.empty();
            $select.append($('<option/>').val('').text('-- Select a Course --'));
            if (res && res.success && res.data && Array.isArray(res.data.courses) && res.data.courses.length) {
                res.data.courses.forEach(function(c) {
                    $select.append($('<option/>').val(c.id).text(c.title + (res.data.remote ? '' : ' [local]')));
                });
                if (current) {
                    $select.val(String(current));
                }
            } else {
                $select.append($('<option/>').val('').text('No courses found'));
            }
        }).fail(function() {
            $select.empty().append($('<option/>').val('').text('Failed to load courses'));
        }).always(function() {
            $select.prop('disabled', false);
        });
    }

    if (window.MLEUploader && typeof MLEUploader.attach === 'function') {
        var $lessonContainer = $('[data-mle-uploader="lesson"]');
        if ($lessonContainer.length) {
            window.__mleLessonUploader = MLEUploader.attach({
                container: $lessonContainer.get(0),
                ajaxConfig: { ajaxurl: mle.ajaxurl, nonce: mle.nonce },
                actions: {
                    extract: 'mle_extract_text_from_upload',
                    submit:  'mle_submit_to_academylm'
                },
                editorId: 'lesson_content',
                titleInputId: 'new_lesson_name',
                courseSelectId: 'select_academylm_course',
                bookNameInputId: 'mle_source_book_name',
                previewSourceId: 'final_generated_content',
                onSubmitted: function(data) {
                    if (data && data.lesson_id) {
                        $('[data-mle-uploader="lesson"] [data-mle-status]').append(' (Lesson ID: ' + data.lesson_id + ')');
                    }
                }
            });
        }

        $('#refresh-academylm-courses').on('click', function(e) {
            e.preventDefault();
            loadAcademyLMCourses(true);
        });

        loadAcademyLMCourses(false);
    }

    /* If AI generation finishes, mirror the generated title into the Book Name field */
    $(document).on('blur change', '#final_generated_title', function() {
        var v = ($(this).val() || '').trim();
        var $bn = $('#mle_source_book_name');
        if (v && $bn.length && !$bn.val()) {
            $bn.val(v);
        }
    });
});
