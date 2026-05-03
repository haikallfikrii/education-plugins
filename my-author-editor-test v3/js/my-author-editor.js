jQuery(document).ready(function($) {

    // --- Fungsionalitas Unggah dan Hapus Foto Penulis ---
    var customUploader;
    $('.browse-author-photo').on('click', function(e) {
        e.preventDefault();

        // Jika uploader sudah ada, buka kembali
        if (customUploader) {
            customUploader.open();
            return;
        }

        // Buat uploader pustaka media WordPress
        customUploader = wp.media.frames.file_frame = wp.media({
            title: 'Select or Upload Author Photo',
            button: {
                text: 'Select Photo'
            },
            multiple: false
        });

        // Saat gambar dipilih, ambil URL dan ID-nya
        customUploader.on('select', function() {
            var attachment = customUploader.state().get('selection').first().toJSON();
            $('#author_photo_id').val(attachment.id);
            $('#author_photo_url').val(attachment.url);
            $('#author-photo-preview').html('<img src="' + attachment.url + '" alt="Author Photo Preview" style="max-width: 150px; height: auto; display: block; margin: 0 auto 5px;" /><button type="button" class="remove-author-photo text-red-500 hover:text-red-700 text-xs mt-1 rounded-none block mx-auto">Remove Image</button>').show();
        });

        // Buka uploader
        customUploader.open();
    });

    // Menangani penghapusan gambar pratinjau
    $(document).on('click', '.remove-author-photo', function(e) {
        e.preventDefault();
        $('#author_photo_id').val('');
        $('#author_photo_url').val('');
        $('#author-photo-preview').hide().html('');
    });


    // --- Fungsionalitas Memuat Konten Penulis yang Ada ---
    $('#load-author-content-btn').on('click', function(e) {
        e.preventDefault();
        var selectedAuthorId = $('#selected_author_id').val();
        
        if (selectedAuthorId) {
            $.ajax({
                url: maeV3EditorAjax.ajaxurl,
                type: 'POST',
                data: {
                    action: 'mae_v3_load_content',
                    nonce: maeV3EditorAjax.nonce,
                    post_id: selectedAuthorId
                },
                beforeSend: function() {
                    // Tampilkan loader atau pesan status
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
                        var data = response.data;
                        
                        // Isi semua field formulir dengan data yang dimuat
                        $('#author_post_id').val(selectedAuthorId);
                        $('#new_author_name').val(data.post_title);
                        $('#author_subtitle').val(data.author_subtitle);
                        $('#footer_position').val(data.footer_position);
                        $('#insert_link').val(data.insert_link);

                        // Isi editor TinyMCE
                        if (tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
                            tinymce.activeEditor.setContent(data.post_content);
                        } else {
                            $('#author_content').val(data.post_content);
                        }
                        
                        // Foto Penulis
                        if (data.author_photo_url) {
                            $('#author_photo_id').val(data.author_photo_id);
                            $('#author_photo_url').val(data.author_photo_url);
                            $('#author-photo-preview').html('<img src="' + data.author_photo_url + '" alt="Author Photo Preview" style="max-width: 150px; height: auto; display: block; margin: 0 auto 5px;" /><button type="button" class="remove-author-photo text-red-500 hover:text-red-700 text-xs mt-1 rounded-none block mx-auto">Remove Image</button>').show();
                        } else {
                            $('#author_photo_id').val('');
                            $('#author_photo_url').val('');
                            $('#author-photo-preview').hide().html('');
                        }

                        // Status Post
                        $('#submit_author_action_select').val(data.post_status);
                        
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

    // --- Konfirmasi Penghapusan dengan SweetAlert2 ---
    $('#author-editor-form').on('submit', function(e) {
        var action = $('#submit_author_action_select').val();
        if (action === 'delete') {
            e.preventDefault();
            var form = this;
            Swal.fire({
                title: 'Are you sure?',
                text: "You won't be able to return this!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Ya, hapus!',
                cancelButtonText: 'Batal'
            }).then((result) => {
                if (result.isConfirmed) {
                    form.submit();
                }
            });
        }
    });

    // Logika untuk menyalin konten dari textarea ke TinyMCE Editor
    $(document).on('click', '#upload_final_content_to_editor', function(e) {
        e.preventDefault();

        // Dapatkan konten dari textarea
        var generatedContent = $('#final_generated_content').val();

        // Periksa apakah TinyMCE sudah diinisialisasi pada editor utama
        if (typeof tinymce !== 'undefined' && tinymce.get('author_content')) {
            // Gunakan API TinyMCE untuk mengatur konten
            tinymce.get('author_content').setContent(generatedContent);
        } else {
            // Jika editor TinyMCE tidak ada (mungkin untuk pengguna tanpa hak akses edit), atur nilai textarea
            $('#author_content').val(generatedContent);
        }
    });
});