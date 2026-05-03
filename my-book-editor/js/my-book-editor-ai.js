(function($) {
    // Book endpoint automation: use book_v3_data (localized) to avoid colliding with other plugins' globals.
    function bookV3Ajax() {
        return (typeof book_v3_data !== 'undefined' && book_v3_data) ? book_v3_data : myBookEditorAjax;
    }

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
    $(document).on('click', '.generate-btn', async function(e) {
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
        var difficultyLevel = $('input[name="difficulty_level"]:checked').val() || 'regular';

        if (!bookName) {
            $statusDiv.text('Please enter book name.').removeClass('hidden').css('color', 'red');
            return;
        }

        var automationPayload = {
            book_name: bookName,
            model: selectedModel,
            task_type: 'book_introduction',
            difficulty_level: difficultyLevel
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
                var payload = response.data && response.data.n8n_response ? response.data.n8n_response : null;
                var mapped = bookEditorExtractN8nFields(payload);

                if (mapped.title || mapped.content) {
                    $('#final_generated_title').val(mapped.title);
                    $('#final_generated_content').val(mapped.content || (payload && typeof payload === 'object' ? JSON.stringify(payload, null, 2) : ''));
                } else if (payload && typeof payload === 'object') {
                    $('#final_generated_title').val('');
                    $('#final_generated_content').val(JSON.stringify(payload, null, 2));
                } else if (payload) {
                    $('#final_generated_title').val('');
                    $('#final_generated_content').val(String(payload));
                } else {
                    $('#final_generated_title').val('');
                    $('#final_generated_content').val('No specific content returned from n8n, but process was successful.');
                }
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
                title: 'Berhasil!',
                text: 'Konten berhasil dimuat ke editor utama.',
                timer: 2000,
                showConfirmButton: false
            });
        } else {
            Swal.fire({
                icon: 'warning',
                title: 'Peringatan',
                text: 'Tidak ada konten yang dihasilkan untuk dimuat.',
                confirmButtonText: 'OK'
            });
        }
    });

    // --- Fungsionalitas Menyimpan Konten sebagai Draf Baru (Automation Section) ---
    $('#save_final_content_as_draft').on('click', function(e) {
        e.preventDefault();

        var title = $('#final_generated_title').val();
        var content = $('#final_generated_content').val();

        if (title.length === 0 || content.length === 0) {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: 'Konten yang dihasilkan kosong. Silakan jalankan otomatisasi terlebih dahulu.',
                confirmButtonText: 'OK'
            });
            return;
        }

        var formData = new FormData();
        formData.append('action', 'my_book_editor_save_final_content_as_draft');
        formData.append('nonce', myBookEditorAjax.nonce);
        formData.append('title', title);
        formData.append('content', content);

        $.ajax({
            url: myBookEditorAjax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: 'Konten berhasil disimpan sebagai draf baru dengan ID ' + response.data.post_id + '.',
                        confirmButtonText: 'OK'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: 'Error: ' + (response.data || 'Terjadi kesalahan yang tidak diketahui.'),
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'AJAX Error: Gagal terhubung ke server.',
                    confirmButtonText: 'OK'
                });
            }
        });
    });

    // --- Fungsionalitas Load Content dari Drafts (Automation Section) ---
    $('#load_content_to_editor_from_draft').on('click', function(e) {
        e.preventDefault();

        var postId = $('#select_title_ce').val();
        var draftType = $('#select_draft_ce').val();

        if (!postId || !draftType) {
            Swal.fire({
                icon: 'error',
                title: 'Gagal',
                text: 'Silakan pilih judul dan draft terlebih dahulu.',
                confirmButtonText: 'OK'
            });
            return;
        }

        $.ajax({
            url: myBookEditorAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'my_book_editor_load_draft_content',
                nonce: myBookEditorAjax.nonce,
                post_id: postId,
                draft_type: draftType
            },
            success: function(response) {
                if (response.success) {
                    var title = response.data.title;
                    var content = response.data.content;

                    if (title.length > 0) {
                        $('#new_book_name').val(title);
                    }

                    if (content.length > 0) {
                        if (tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
                            tinymce.activeEditor.setContent(content);
                        } else {
                            $('#book_content').val(content);
                        }
                    }

                    Swal.fire({
                        icon: 'success',
                        title: 'Berhasil!',
                        text: 'Konten draft berhasil dimuat ke editor utama.',
                        confirmButtonText: 'OK'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Gagal',
                        text: 'Error: ' + (response.data || 'Terjadi kesalahan yang tidak diketahui.'),
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Gagal',
                    text: 'AJAX Error: Gagal memuat konten draft.',
                    confirmButtonText: 'OK'
                });
            }
        });
    });

    // Tampilkan/sembunyikan dropdown draft saat judul dipilih
    $('#select_title_ce').on('change', function() {
        if ($(this).val()) {
            $('#draft_selection_container').removeClass('hidden');
        } else {
            $('#draft_selection_container').addClass('hidden');
        }
    });
})(jQuery);
