/**
 * Lesson Plan tree (right column): grouped by book, checkboxes, bulk LMS submit.
 */
(function($) {
    'use strict';

    var mle = window.mylessonEditorAjax || window.myLessonPlanEditorAjax || {};
    var expandedBooks = {};
    var expandedGroups = {};

    function escapeHtml(value) {
        return String(value == null ? '' : value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

    function setTreeStatus(message, type) {
        var $el = $('#mle_lesson_tree_status');
        if (!$el.length) {
            return;
        }
        $el.removeClass('hidden text-green-700 text-red-700 text-gray-600');
        if (!message) {
            $el.addClass('hidden').text('');
            return;
        }
        if (type === 'success') {
            $el.addClass('text-green-700');
        } else if (type === 'error') {
            $el.addClass('text-red-700');
        } else {
            $el.addClass('text-gray-600');
        }
        $el.text(message).removeClass('hidden');
    }

    function getSelectedIds() {
        var ids = [];
        $('#mle-lesson-tree .mle-lesson-item-checkbox:checked').each(function() {
            ids.push(String($(this).val()));
        });
        return ids;
    }

    function syncBookSelectAll($bookNode) {
        if (!$bookNode || !$bookNode.length) {
            return;
        }
        var $items = $bookNode.find('.mle-lesson-item-checkbox');
        var $checked = $items.filter(':checked');
        var $selectAll = $bookNode.find('.mle-book-select-all').first();
        if (!$selectAll.length) {
            return;
        }
        if (!$items.length) {
            $selectAll.prop('checked', false).prop('indeterminate', false);
            return;
        }
        $selectAll.prop('checked', $items.length === $checked.length);
        $selectAll.prop('indeterminate', $checked.length > 0 && $checked.length < $items.length);
    }

    function syncAllBookSelectAll() {
        $('#mle-lesson-tree .mle-book-node').each(function() {
            syncBookSelectAll($(this));
        });
    }

    function renderTree(data) {
        var $root = $('#mle-lesson-tree');
        if (!$root.length) {
            return;
        }

        var books = (data && data.books) ? data.books : [];
        if (!books.length) {
            $root.html('<p class="caast-meta">' + escapeHtml('No lesson plans found.') + '</p>');
            return;
        }

        var html = '';
        books.forEach(function(book, bookIndex) {
            var bookKey = 'book-' + bookIndex;
            var bookOpen = expandedBooks[bookKey] !== false;
            var selectAllId = 'mle-book-select-all-' + bookIndex;
            html += '<div class="mle-book-node" data-book-key="' + escapeHtml(bookKey) + '">';
            html += '<button type="button" class="mle-tree-toggle mle-book-toggle" data-target="' + escapeHtml(bookKey) + '" aria-expanded="' + (bookOpen ? 'true' : 'false') + '">';
            html += '<span class="mle-tree-chevron">' + (bookOpen ? '▼' : '▶') + '</span>';
            html += '<span class="mle-tree-label">' + escapeHtml(book.title || 'Untitled book') + '</span>';
            html += '</button>';

            html += '<div class="mle-book-children' + (bookOpen ? '' : ' mle-is-collapsed') + '" data-book-panel="' + escapeHtml(bookKey) + '">';
            html += '<label class="mle-lesson-tree-select-all mle-book-select-all-label" for="' + escapeHtml(selectAllId) + '">';
            html += '<input type="checkbox" class="mle-book-select-all" id="' + escapeHtml(selectAllId) + '" data-book-key="' + escapeHtml(bookKey) + '" />';
            html += '<span>' + escapeHtml('Select All') + '</span>';
            html += '</label>';

            (book.groups || []).forEach(function(group, groupIndex) {
                var groupKey = bookKey + '-group-' + groupIndex;
                var groupOpen = expandedGroups[groupKey] !== false;
                html += '<div class="mle-group-node" data-group-key="' + escapeHtml(groupKey) + '">';
                html += '<button type="button" class="mle-tree-toggle mle-group-toggle" data-target="' + escapeHtml(groupKey) + '" aria-expanded="' + (groupOpen ? 'true' : 'false') + '">';
                html += '<span class="mle-tree-chevron">' + (groupOpen ? '▼' : '▶') + '</span>';
                html += '<span class="mle-tree-label">' + escapeHtml(group.label || 'Lessons') + '</span>';
                html += '</button>';

                html += '<ul class="mle-lesson-list' + (groupOpen ? '' : ' mle-is-collapsed') + '" data-group-panel="' + escapeHtml(groupKey) + '">';
                (group.items || []).forEach(function(item) {
                    var statusLabel = item.status ? ' (' + item.status + ')' : '';
                    var lmsBadge = item.lms_id ? ' <span class="mle-lms-badge">LMS</span>' : '';
                    var inputId = 'mle-lesson-cb-' + String(item.id);
                    var labelText = escapeHtml(item.label || item.title || 'Untitled') + escapeHtml(statusLabel);
                    html += '<li class="mle-lesson-item">';
                    html += '<div class="mle-lesson-item-row">';
                    html += '<input type="checkbox" class="mle-lesson-item-checkbox" id="' + escapeHtml(inputId) + '" value="' + escapeHtml(String(item.id)) + '" />';
                    html += '<label class="mle-lesson-item-text" for="' + escapeHtml(inputId) + '">' + labelText + lmsBadge + '</label>';
                    html += '</div>';
                    html += '</li>';
                });
                html += '</ul>';
                html += '</div>';
            });
            html += '</div>';
            html += '</div>';
        });

        $root.html(html);
        syncAllBookSelectAll();
    }

    function refreshLessonTree(selectedId) {
        if (!$('#mle-lesson-tree').length || !mle.ajaxurl || !mle.nonce) {
            return $.Deferred().resolve().promise();
        }

        return $.ajax({
            url: mle.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'my_lesson_editor_get_grouped_lessons',
                nonce: mle.nonce
            }
        }).done(function(response) {
            if (response && response.success && response.data) {
                renderTree(response.data);
                if (selectedId) {
                    $('#mle-lesson-tree .mle-lesson-item-checkbox[value="' + String(selectedId) + '"]').prop('checked', true);
                    syncAllBookSelectAll();
                }
            } else {
                $('#mle-lesson-tree').html('<p class="caast-meta text-red-700">' + escapeHtml('Could not load lesson plans.') + '</p>');
            }
        }).fail(function() {
            $('#mle-lesson-tree').html('<p class="caast-meta text-red-700">' + escapeHtml('Failed to load lesson plans.') + '</p>');
        });
    }

    function loadLessonIntoEditor(postId) {
        return $.ajax({
            url: mle.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'my_lesson_editor_load_draft_content',
                nonce: mle.nonce,
                post_id: postId,
                draft_type: 'main'
            }
        }).done(function(response) {
            if (response && response.success && response.data) {
                var title = response.data.title || '';
                var content = response.data.content || '';
                if (title) {
                    $('#new_lesson_name').val(title);
                }
                if ($('#lesson_post_id').length) {
                    $('#lesson_post_id').val(String(postId));
                }
                if ($('#mle_select_title_ce').length) {
                    $('#mle_select_title_ce').val(String(postId));
                }
                if (content) {
                    if (typeof tinymce !== 'undefined' && tinymce.get('lesson_content')) {
                        tinymce.get('lesson_content').setContent(content);
                    } else {
                        $('#lesson_content').val(content);
                    }
                }
                if (typeof Swal !== 'undefined') {
                    Swal.fire({ icon: 'success', title: 'Loaded', timer: 1600, showConfirmButton: false });
                }
            } else {
                alert((response && response.data) ? response.data : 'Load failed.');
            }
        });
    }

    function submitSelectedToLms() {
        var ids = getSelectedIds();
        if (!ids.length) {
            setTreeStatus('Select at least one lesson.', 'error');
            return;
        }

        var courseId = ($('#select_academylm_course').val() || '').trim();
        if (!courseId) {
            setTreeStatus('Select a course in the Course & file upload section first.', 'error');
            return;
        }

        var $btn = $('#mle_submit_selected_lms');
        $btn.prop('disabled', true).addClass('opacity-60');
        setTreeStatus('Submitting ' + ids.length + ' lesson(s) to LMS…', 'info');

        $.ajax({
            url: mle.ajaxurl,
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'mle_bulk_submit_to_academylm',
                nonce: mle.nonce,
                course_id: courseId,
                post_ids: ids
            }
        }).done(function(response) {
            if (response && response.success && response.data) {
                setTreeStatus(response.data.message || 'Submitted to LMS.', 'success');
                refreshLessonTree();
            } else {
                var msg = (response && response.data && response.data.message) ? response.data.message : 'LMS submit failed.';
                setTreeStatus(msg, 'error');
            }
        }).fail(function() {
            setTreeStatus('LMS submit request failed.', 'error');
        }).always(function() {
            $btn.prop('disabled', false).removeClass('opacity-60');
        });
    }

    $(document).ready(function() {
        if (!$('#mle-lesson-tree').length) {
            return;
        }

        refreshLessonTree();

        $(document).on('mle:drafts-changed', function(e, selectedId) {
            refreshLessonTree(selectedId);
        });

        $(document).on('change', '#mle-lesson-tree .mle-book-select-all', function() {
            var checked = $(this).is(':checked');
            var $book = $(this).closest('.mle-book-node');
            $book.find('.mle-lesson-item-checkbox').prop('checked', checked);
            syncBookSelectAll($book);
        });

        $(document).on('change', '#mle-lesson-tree .mle-lesson-item-checkbox', function() {
            syncBookSelectAll($(this).closest('.mle-book-node'));
        });

        $(document).on('click', '.mle-book-toggle, .mle-group-toggle', function(e) {
            e.preventDefault();
            var target = $(this).data('target');
            var isBook = $(this).hasClass('mle-book-toggle');
            var $panel = isBook
                ? $('[data-book-panel="' + target + '"]')
                : $('[data-group-panel="' + target + '"]');
            var willOpen = $panel.hasClass('mle-is-collapsed');
            $panel.toggleClass('mle-is-collapsed');
            $(this).find('.mle-tree-chevron').text(willOpen ? '▼' : '▶');
            $(this).attr('aria-expanded', willOpen ? 'true' : 'false');
            if (isBook) {
                expandedBooks[target] = willOpen;
            } else {
                expandedGroups[target] = willOpen;
            }
        });

        $('#mle_load_selected_lessons').on('click', function(e) {
            e.preventDefault();
            var ids = getSelectedIds();
            if (ids.length !== 1) {
                setTreeStatus('Select exactly one lesson to load into the editor.', 'error');
                return;
            }
            setTreeStatus('', '');
            loadLessonIntoEditor(ids[0]);
        });

        $('#mle_submit_selected_lms').on('click', function(e) {
            e.preventDefault();
            submitSelectedToLms();
        });

        window.mleRefreshLessonTree = refreshLessonTree;
    });
})(jQuery);
