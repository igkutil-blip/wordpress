/* Plan A: dio WpTravellyjevog assets/admin/ttbm_admin_script.js (2.3.4, GPL) – samo upiti (Enquiry). */
(function ($) {
    // ================Get Enquiry=================//
    $(document).ready(function ($) {
        // Only run tab logic on our plugin's settings page
        if ($('#ttbm-settings-page').length) {
            const tabs = $('#ttbm-settings-page .nav-tab');
            const contents = $('#ttbm-settings-page .tab-content');
            tabs.on('click', function (e) {
                e.preventDefault();
                tabs.removeClass('nav-tab-active');
                contents.hide();
                $(this).addClass('nav-tab-active');
                const target = $(this).attr('href');
                if (target && target.startsWith('#')) {
                    $(target).show();
                }
            });
        }
    });
    $(document).on('click', '.ttbm-delete-enquiry', function (e) {
        e.preventDefault();
        let isConfirmed = confirm('Are you sure you want to delete this row?');
        if (isConfirmed) {
            let row = $(this).closest('tr');
            let enquiryId = $(this).data('id');
            $.ajax({
                url: ttbm_admin_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'ttbm_delete_enquiry',
                    enquiry_id: enquiryId,
                    nonce: ttbm_admin_ajax.nonce
                },
                success: function (response) {
                    if (response.success) {
                        row.remove();
                    } else {
                        alert('Failed to delete the enquiry. Please try again.');
                    }
                },
                error: function (error) {
                    console.log('Error:', error);
                    alert('An error occurred while deleting the enquiry.');
                }
            });
        }
    });
    $(document).on('click', '.ttbm-reply-enquiry', function (e) {
        e.preventDefault();
        let enquiryId = $(this).data('id');
        var row = $(this).closest('.ttbm-enquiry-list');
        var subject = row.find('td:eq(0)').text().trim();
        var name = row.find('td:eq(1)').text().trim();
        var email = row.find('td:eq(2)').text().trim();
        $('#ttbm-post-id').val(enquiryId);
        $('#ttbm-reply-to').val(email);
        $('#ttbm-reply-subject').val(subject);
    });
    $('#ttbm-reply-enquiry-form').on('submit', function (e) {
        e.preventDefault();
        if (typeof tinyMCE !== 'undefined' && tinyMCE.get('ttbm-reply-message')) {
            tinyMCE.get('ttbm-reply-message').save();
        }
        var formData = $(this).serialize();
        $.ajax({
            type: "POST",
            url: ttbm_admin_ajax.ajax_url,
            data: {
                action: 'ttbm_reply_enquiry',
                nonce: ttbm_admin_ajax.nonce,
                data: formData
            },
            success: function (response) {
                console.log(response);
                if (response.success) {
                    $('.reply-ajax-response').html(response.data.message).css('color', 'green');
                } else {
                    $('.reply-ajax-response').html(response.data.message).css('color', 'red');
                }
            },
            error: function (error) {
                console.log('Error:', error);
                alert('An error occurred while loading the enquiry details.');
            }
        });
    });
    // ==========view enquiry=============
    $(document).on('click', '.ttbm-view-enquiry', function (e) {
        e.preventDefault();
        let enquiryId = $(this).data('id');
        $.ajax({
            url: ttbm_admin_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'ttbm_view_enquiry',
                enquiry_id: enquiryId,
                nonce: ttbm_admin_ajax.nonce
            },
            success: function (response) {
                if (response.success) {
                    $('.ttbm-view-enquiry-response').html(response.data.html);
                } else {
                    alert('Failed to load the enquiry details. Please try again.');
                }
            },
            error: function (error) {
                console.log('Error:', error);
                alert('An error occurred while loading the enquiry details.');
            }
        });
    });
    $(document).on('click', '#ttbm-enquiry-modal .modal-close', function () {
        $('#ttbm-enquiry-modal').removeClass('open');
    });
}(jQuery));
