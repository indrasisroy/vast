var files;
var uploadedDataOutput, identifyTwitterClick, identifyTumblrClick, identifyFbClick, identifyGplusClick;
var ajaxInProgress = false;
$(document).ready(function() {
    $("#disable_app").bootstrapSwitch();
    $('#disable_app').on('switchChange.bootstrapSwitch', function (event, state) {
        $('#app_network_disabled').val($(this).bootstrapSwitch('state') === true ? 1 : 0);
    }); 
    var loadingEOF = false;
    var add_upload_type, add_cover_type;

    if (isPhone()) {
        $(".left-sidebar").hide();
        $(".left-sidebar-mobile").show();
        $("#ext-view .signup-content, #ext-view .app-store").hide();
        $("#ext-view-mob").show();
		$(".see-more-vast").show();
    } else {
        $(".left-sidebar-mobile").hide();
        $(".left-sidebar").show();
        $("#ext-view-mob").hide();
        $("#ext-view .signup-content, #ext-view .app-store").show();
    }

    $('#confirmDelete').find('.modal-footer #confirm').on('click', function() {
        $(this).addClass('wait_symbol');
        var formData = $(this).data('form').serialize();
        var form = $($(this).data('form').get(0))
        var formUrl = form.attr('action');
        $.ajax({
            type: 'POST',
            url: formUrl,
            data: formData
        }).done(function(msg) {
            $('#confirmDelete').modal('hide');

            if (msg == 1) {
                form.parents('.post-container-outer').fadeOut(function() {
                    $(this).remove();
                });
            }
            $('.modal-footer #confirm').removeClass('wait_symbol');
            getPostFeeds(true);
        });
        return false;
    });

   
    $('.toggle-secondary').on('click', function() {
        toggleSecondary();
    });

  

    $('#fb_login').on('click', function() {
        var desc = $('#add_livestream').css('display') == 'block' ? $('#add_livestream #txtStory').val() : $('#step5 #txtStory').val();
        var title = $('#add_livestream').css('display') == 'block' ? 'Vast' : $('#inputtitle').val();
        var image = $('#add_livestream').css('display') == 'block' ? $('#add_livestream #secondary_image_post').attr('src') : $('#step5 .add-post-music-image').attr('src');
        $("meta[property='og:title']").attr("content", title);
        $("meta[property='og:description']").attr("content", desc);
        $("meta[property='og:image']").attr("content", image);

    });   
	
	$('#fbSocialShare').bind('click', function(e) {
        //console.log($(this).parent().attr('href'))
		e.preventDefault();
		fbWin = window.open($(this).parent().attr('href'), 'FB Share', "status=no,height=530,width=1115,resizable=yes,toolbar=no,menubar=no,scrollbars=no,location=no,directories=no,top=93,left=157"); 
	});
	$('#twSocialShare').bind('click', function(e) {
		e.preventDefault();
		twWin = window.open($(this).parent().attr('href'), 'Twitter Share', "status=no,height=530,width=1115,resizable=yes,toolbar=no,menubar=no,scrollbars=no,location=no,directories=no,top=93,left=157");           
	});
 

    function toggleSecondary() {
        var displayValue = $('.secondary-content').css('display');
        if (displayValue == 'none') {
            $('#feedPopup .feed-popup-player').css('display', 'none');
        } else {
            $('#feedPopup .feed-popup-player').css('display', 'block');
        }
    }

    
    $('#confirmDelete').on('show.bs.modal', function(e) {
        $message = $(e.relatedTarget).attr('data-message');
        $(this).find('.modal-body p').text($message);
        $title = $(e.relatedTarget).attr('data-title');
        $(this).find('.modal-title').text($title);

        // Pass form reference to modal for submission on yes/ok
        var form = $(e.relatedTarget).closest('form');
        $(this).find('.modal-footer #confirm').data('form', form);
    });

    /* media element pause on media popup close starts */

   /* $('#feedPopup').on('hidden.bs.modal', function() {
            if ($('#popupVideoId').length > 0)
                $('#popupVideoId').trigger("pause");
            if ($('#popupAudioId').length > 0)
                $('#popupAudioId').trigger("pause");
            if ($('#secpopupVideoId').length > 0)
                $('#secpopupVideoId').trigger("pause");
            if ($('#secpopupAudioId').length > 0)
                $('#secpopupAudioId').trigger("pause");

        })
   
   */
        /* media element pause on media popup close ends */

    $('#updateVastName').bind('click', function() {
        $.ajax({
            type: 'POST',
            url: '/ajax/update-vast-name',
            data: {
                vastName: $('#inputprofile-name').val()
            }
        }).done(function(msg) {
            if (msg == 1) {
                //alert('Success');
                showAlert('success', 'VAST name, being updated');
            } else {
                //alert('Failure');
                showAlert('danger', 'A problem has been occurred while submitting your data.');
            }
        });
        return false;
    });
    
  
	
	 $('.nav_cricles_div').delegate('.nav_visited', 'click', function() {
     	$('#step' + $(this).text()).show().nextAll('section').hide();
     });

    if ($('#feed-contents').length > 0) {
        $('.feed-date').bind('click', function() {
            $(this).addClass('active');
            $('.feed-chapter').removeClass('active');
            getPostFeeds(false);
            loadingEOF = false;
            return false;
        });

        $('.feed-chapter').bind('click', function() {
            $(this).addClass('active');
            $('.feed-date').removeClass('active');
            getPostFeeds(false);
            loadingEOF = false;
            return false;
        });

        $(window).bind('scroll', function() {
            if ($(window).scrollTop() >= $(document).height() - $(window).height()) {
                if ($('.loading-post').css('display') == 'block' || loadingEOF == true)
                    return false;

                $('.loading-post').show();
                getPostFeeds(true);
            }
        });
    }

  

    // edit post starts here

    $("#editPostForm").submit(function(e) {		
        if ($.active > 0)
            return false;
        var publishTime = $('input[name=schedules]:checked', '#editPostForm').val();
        var date, time;
        date = $('#inputpublishdate').val();
        time = $('#inputpublishtime').val();
        e.preventDefault();
        var formData = new FormData($(this)[0]);
        formData.append('publish-date', date);
        formData.append('publish-time', time);
        formData.append('publishTime', publishTime);
        formData.append('fb_page_data', $('#fb_page_data').val());
        $.ajax({
            type: 'POST',
            url: '/ajax/edit-post',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                $("#validation-errors").hide().empty();
                $(".account-save").attr("disabled", true);
            },
        }).done(function(msg) {
            $(".account-save").removeAttr("disabled");
            if (msg.success == false) {
                var arr = msg.errors;
                var errMsg = "";
                $.each(arr, function(index, value) {
                    if (value.length != 0) {
                        errMsg += value + "<br/>";

                    }
                });
                $("#validation-errors").append('<div class="alert alert-danger">' + errMsg + '<div>');
                $("#validation-errors").show();
                $('#myModalLabel').html('Errors');
                $('#success_message').hide();
                $('.change_but').attr('id', 'confirm_message_failure');
                $('#myModalMessage').modal('show');
            }
            if (msg == 1) {
                $('#myModalLabel').html('Success');
                $("#validation-errors").hide();
                $('#success_message').show();
                $('.change_but').attr('id', 'confirm_message_success');
                $('#myModalMessage').modal('show');
            }
        });

        return false;
    });

    $('#myModalMessage').delegate('#confirm_message_success', 'click', function() {
       
		if($('#post_source_type').val() == 1){
			 location.href = "/cms/channel/chapter/post/"+$('#post_id').val()+"/edit";
		}else{
			 location.href = "/cms/feeds";
		}
		return false;
    });


    // edit post ends here


    function getPostFeeds(load_more) {
        var order = 'date';
        var chapter = '';
        if ($('.feed-chapter').hasClass('active')) {
            order = 'chapter_name';
            if (load_more)
                chapter = $('h3.chapter_name').last().text().replace('#', '');
        }

        var offset = 0;

        if (load_more == true) {
            offset = $('.post-container').length;
        }

        $.ajax({
            type: 'GET',
            url: '/ajax/get-feeds',
            data: {
                order: order,
                offset: offset,
                last_chapter: chapter
            }
        }).done(function(msg) {
            if (load_more == true) {
                $('#feed-contents').append(msg);
                $('.loading-post').hide();
                if (msg == '')
                    loadingEOF = true;

                $('.post-clickable').on('click', function() {
                    Scripts.showPostsPopup(this);
                });

            } else {
                $('#feed-contents').html(msg);
            }
        });
    }


   
    $('.share-add-chapter').bind('click', function() {
        $('#section-add-chapter').slideDown('slow');
        return false;
    });

    $(".show-chap-dropdown").click(function(e) {


        if ($(".show-chap-dropdown").hasClass('active')) {
            $(".chap-arrow").html('<img src="/images/right.png" class="" style="width:10px" alt="">');
            $(".chapter_dropdown").slideUp('slow');
            $(".show-chap-dropdown").removeClass('active');
        } else {
            $(".chap-arrow").html('<img src="/images/down.png" class="" style="width:10px" alt="">');
            $(".chapter_dropdown").slideDown('slow');
            $(".show-chap-dropdown").addClass('active');
        }
    });
    $(".show-publish-dropdown").click(function() {
        if ($(".show-publish-dropdown").hasClass('active')) {
            $(".publish-arrow").html('<img src="/images/right.png" class="" style="width:10px" alt="">');
            $('.publish-dropdown').slideUp('slow');
            $(".show-publish-dropdown").removeClass('active');
        } else {
            $(".publish-arrow").html('<img src="/images/down.png" class="" style="width:10px" alt="">');
            $('.publish-dropdown').slideDown('slow');
            $(".show-publish-dropdown").addClass('active');
        }
    });

    /*
    	Edit page subscription change script start
    */
    $('#all-user').click(function() {
        if ($(this).is(':checked')) {
            $('.edit-paying-subscription .edit-msgRadioText').css('color', '#ffffff');
            $(this).parent().find('.edit-msgRadioText').css('color', '#ffffff');
        }
        $('#post_paying_subscriber').attr('value', 0);
    });

    $('#paying-sub').click(function() {
        if ($(this).is(':checked')) {
            $('.edit-paying-subscription .edit-msgRadioText').css('color', '#ffffff');
            $(this).parent().find('.edit-msgRadioText').css('color', '#ffffff');
        }
        $('#post_paying_subscriber').attr('value', 1);
    });
    /*
    	Edit page subscription change script end
    */


    /* Message user selection start */
    $('#msg-users1').click(function() {
        if ($(this).is(':checked')) {
            $('.msgRadioText').css('color', '#fff');
            $(this).parent().find('.msgRadioText').css('color', '#ffffff');
        }
    });
    $('#msg-users2').click(function() {
        if ($(this).is(':checked')) {
            $('.msgRadioText').css('color', '#fff');
            $(this).parent().find('.msgRadioText').css('color', '#ffffff');
        }
    });
    $('#msg-users3').click(function() {
        if ($(this).is(':checked')) {
            $('.msgRadioText').css('color', '#fff');
            $(this).parent().find('.msgRadioText').css('color', '#ffffff');
        }
    });
    /* Message user selection end */

    $('.pay-sub-group #pay-sub-yes').click(function() {
        if ($('.pay-sub-group #pay-sub-yes').hasClass('pay-sub-selected')) {
            $('.pay-sub-group #pay-sub-yes').removeClass('pay-sub-selected');
            $('.pay-sub-group #pay-sub-no').addClass('pay-sub-selected');
            $('.pay-sub-group .pay-sub-div').css('left', 'auto');
            $('.pay-sub-group .pay-sub-div').css('right', '0');
        } else {
            $('.pay-sub-group #pay-sub-no').removeClass('pay-sub-selected');
            $('.pay-sub-group #pay-sub-yes').addClass('pay-sub-selected');
            $('.pay-sub-group .pay-sub-div').css('right', 'auto');
            $('.pay-sub-group .pay-sub-div').css('left', '0');
        }
        return false;
    });


    /* Alert Messages ( success , failure warnnig )*/
    function showAlert(alertType, message) {
        if (alertType == 'danger') {
            error_head = 'Error';
        } else if (alertType == 'info') {
            error_head = 'Note';
        } else {
            error_head = 'Success';
        }

        $('#errorContainer').fadeIn('slow', function() {
            $("#errorContainer").html('<div class="alert alert-' + alertType + '"><a href="#" class="close" data-dismiss="alert">&times;</a><strong>' + error_head + '!</strong> ' + message + '</div>');
        });
        setTimeout(function() {
            $('#errorContainer').fadeOut('slow');
        }, 4000);
    }

    $('#deleteMp3').bind('click', function() {
        $.ajax({
            type: 'POST',
            url: '/ajax/delete-music',
            data: {
                post_id: $("input[name*='post_id']").val()
            }
        }).done(function(msg) {
            if (msg == 1) {
                //alert('Success');
                showAlert('success', 'VAST name, being updated');
            } else {
                //alert('Failure');
                showAlert('danger', 'A problem has been occurred while submitting your data.');
            }
        });
        return false;
    });

   
    // secondary music upload for addpost ends here 

    $('#delete_uploaded_music').bind('click', function() {
        $('#post_music').attr("data-prevdata", $('#post_music').val());
        $('#post_music').attr('value', '');
        $('#uploaded_music_button').hide();
        $('#upload_music_button').show();
        return false;
    });

    $('#delete_uploaded_photo').bind('click', function() {
        $('#post_photo').attr("data-prevdata", $('#post_photo').val());
        $('.image_parameters').attr('value', 0);
        $('#uploaded_photo_button').hide();
        $('#post_photo').attr('value', '');
        $('#upload_photo_button').show();
        return false;
    });


    $('#delete_uploaded_video').bind('click', function() {
        $('#post_video').attr("data-prevdata", $('#post_video').val());
        $('#uploaded_video_button').hide();
        $('#post_video').attr('value', '');
        $('#upload_video_button').show();
        return false;
    });

    $('#delete_uploaded_music_image').bind('click', function() {
        $('#post_music_image').attr("data-prevdata", $('#post_music_image').val());
        $('.image_parameters').attr('value', 0);
        $('#uploaded_music_image_button').hide();
        $('#post_music_image').attr('value', '');
        $('#upload_music_image_button').show();
        return false;
    });

    $('#delete_uploaded_image_video').bind('click', function() {
        $('#upload_image_video_button #btn_feed_image_video_upload').html("UPLOAD FILE");
        $('#post_image_video').attr("data-prevdata", $('#post_image_video').val());
        $('#uploaded_image_video_button').hide();
        $('#post_image_video').attr('value', '');
        $('#upload_image_video_button').show();
        return false;
    });

    $('#delete_uploaded_video_video').bind('click', function() {
        $('#upload_video_video_button #btn_feed_video_video_upload').html("UPLOAD FILE");
        $('#post_video_video').attr("data-prevdata", $('#post_video_video').val());
        $('#uploaded_video_video_button').hide();
        $('#post_video_video').attr('value', '');
        $('#upload_video_video_button').show();
        return false;
    });

    $('#delete_uploaded_image_audio').bind('click', function() {
        $('#post_image_audio').attr("data-prevdata", $('#post_image_audio').val());
        $('#uploaded_image_audio_button').hide();
        $('#post_image_audio').attr('value', '');
        $('#upload_image_audio_button').show();
        return false;
    });

    $('#delete_uploaded_video_audio').bind('click', function() {
        $('#post_video_audio').attr("data-prevdata", $('#post_video_audio').val());
        $('#uploaded_video_audio_button').hide();
        $('#post_video_audio').attr('value', '');
        $('#upload_video_audio_button').show();
        return false;
    });

    $('#delete_uploaded_music_video').bind('click', function() {
        $('#upload_music_video_button #btn_feed_music_video_upload').html('UPLOAD FILE');
        $('#post_music_video').attr("data-prevdata", $('#post_music_video').val());
        $('#uploaded_music_video_button').hide();
        $('#post_music_video').attr('value', '');
        $('#upload_music_video_button').show();
        return false;
    });

    $('#delete_uploaded_tcvideo_xml').bind('click', function() {
        $('#uploaded_tcvideo_xml_button').hide();
        $('#post_video_xml').attr('value', '');
        $('#upload_tcvideo_xml_button').show();
        return false;
    });

    $('#delete_uploaded_video_thumb').bind('click', function() {
        $('#post_video_thumb').attr("data-prevdata", $('#post_video_thumb').val());
        $('#uploaded_video_thumb_button').hide();
        $('#post_video_thumb').attr('value', '');
        $('#upload_video_thumb_button').show();
        return false;
    });

    $('#delete_uploaded_channelgroup_cover').bind('click', function() {
        $('#uploaded_channelgroup_cover_button').hide();
        $('#channelgroup_cover_image').attr('value', '');
        $('#upload_channelgroup_cover_button').show();
        return false;
    });


    $('#add_chapter').bind('click', function() {
        $('#section-message').toggle('slow');
    });

    $('#message_add_chapter').bind('click', function() {
        $('#message-section-message').slideDown('slow');
    });

    $('#confirm_delete').bind('click', function() {
        $('#deleteMessage').submit();
    });

    $('#add_chapter_button').bind('click', function() {
		
        var formData = "chapterName=" + $('#section-message #new-chapter').val();
        if ($.trim($('#section-message #new-chapter').val()) != "") {
            $.ajax({
                type: 'POST',
                url: '/ajax/add-chapter',
                data: formData
            }).done(function(msg) {
				if (msg.success == false) {
                    var arr = msg.errors;
                    var errMsg = "";
                    $.each(arr, function(index, value) {
                        if (value.length != 0) {
                            errMsg += value + "<br/>";

                        }
                    });
                    $("#validation-errors").html('<div class="alert alert-danger">' + errMsg + '<div>');
                    $("#validation-errors").show();
                    $('#myModalMessage').modal('show');
                }else if(msg.success == true){
                    var chapterText = $('#new-chapter').val();
                    $('#cmbChapter option').prop("selected", false);
                    $("#cmbChapter").append("<option value='" + msg.chapter_id + "' selected='selected'>" + chapterText + "</option>");
                    $('#new-chapter').val('');
                    $('#section-message').slideUp('slow');

                }
            });
        }
        return false;
    });
	
  

    $('#chapter_button_new').bind('click', function() {
        var formData = "chapterName=" + $('#section-add-chapter-new #new-chapter').val();
        if ($.trim($('#section-add-chapter-new #new-chapter').val()) != "") {
            $.ajax({
                type: 'POST',
                url: '/ajax/add-chapter',
                data: formData
            }).done(function(msg) {
				 if (msg.success == false) {
                    var arr = msg.errors;
                    var errMsg = "";
                    $.each(arr, function(index, value) {
                        if (value.length != 0) {
                            errMsg += value + "<br/>";

                        }
                    });
                    $("#validation-errors").html('<div class="alert alert-danger">' + errMsg + '<div>');
                    $("#validation-errors").show();
                    $('#myModalMessage').modal('show');
                }else if(msg.success == true){
                    var chapterText = $('#section-add-chapter-new #new-chapter').val();
                    $('#cmbChapterShareNew option').prop("selected", false);
                    $("#cmbChapterShareNew").append("<option value='" + msg.chapter_id + "' selected='selected'>" + chapterText + "</option>");
                    $('#section-add-chapter-new #new-chapter').val('');
                    $('#section-add-chapter-new').slideUp('slow');
                }
            });
        }
        return false;
    });

    $('#pay-sub-yes').bind('click', function() {
        $('#post_paying_subscriber').val('1');
    });
    $('#pay-sub-no').bind('click', function() {
        $('#post_paying_subscriber').val('0');
    });
   


    $('.inputpublishdate').datetimepicker({
        timepicker: false,
        format: 'm/d/Y',
        formatDate: 'm/d/Y',
        //minDate:0, // yesterday is minimum date
        onChangeDateTime: function() {
            $('.inputpublishdate').datetimepicker('hide');
        }
    });

    $('.inputpublishtime').datetimepicker({
        datepicker: false,
        format: 'H:i',
        step: 5
    });

    /* for poi starts here */
    $('#poi-img-modal').imgAreaSelect({
        maxWidth: 1,
        maxHeight: 1,
        handles: true,
        autoHide: true,
        onSelectEnd: function(img, selection) {
            $('#poi_coords').val(selection.x1 + ',' + selection.y1);
            $('.add_post_poi_position').css('margin-left', (selection.x1 - 15) + 'px');
            $('.add_post_poi_position').css('margin-top', (selection.y1 - 15) + 'px');
            $('.add_post_poi_position').css('display', 'block');
        }
    });


    /* poi for edit post starts here */
    $('#secondary_image_post').imgAreaSelect({
        maxWidth: 1,
        maxHeight: 1,
        handles: true,
        autoHide: true,
        onSelectEnd: function(img, selection) {
            $('#poixy').val(selection.x1 + ',' + selection.y1);
            $('.poi_position').css('margin-left', (selection.x1 - 15) + 'px');
            $('.poi_position').css('margin-top', (selection.y1 - 15) + 'px');
            var formData = new FormData();
            formData.append('poi', $('#poixy').val());
            formData.append('poiUrl', $('#secondary_image_post').attr('src'));
            formData.append('cWidth', $('#secondary_image_post').width());
            formData.append('cHeight', $('#secondary_image_post').height());
            if ($('#poixy').val() != "") {
                $.ajax({
                    type: 'POST',
                    url: '/ajax/poi-coords',
                    data: formData,
                    processData: false,
                    contentType: false,
                }).done(function(resp) {
                    $('#poi_coords').val(resp);

                });
            }

        }
    });

    /* poi for edit post ends here */

    $('#feedPopupSecondary').delegate('.close', 'click', function() {
        if ($('#popupAudioId').length > 0) {
            var mediaElement = document.getElementById("popupAudioId");
            mediaElement.pause();
            mediaElement.removeAttribute("src");
        }
        if ($('#popupVideoId').length > 0) {
            var videoElement = document.getElementById("popupVideoId");
            videoElement.pause();
            videoElement.removeAttribute("src");
        }
    });

    $('#confirm_poi_selection').bind('click', function() {
        var formData = new FormData();
        formData.append('poi', $('#poi_coords').val());
        formData.append('poiUrl', $('#poi-img-modal').attr('src'));
        formData.append('cWidth', $('#poi-img-modal').width());
        formData.append('cHeight', $('#poi-img-modal').height());
        if ($('#poi_coords').val() != "") {
            $.ajax({
                type: 'POST',
                url: '/ajax/poi-coords',
                data: formData,
                processData: false,
                contentType: false,
            }).done(function(resp) {
                $('#poi_coords').val(resp);
            });
        }

    });
  


		$('#vip_event_add').bind('click',function(){
			add_upload_type = 'event';
		});


}); //document ready ends here ...



$("#admin_change_password").click(function() {
    if ($("#admin_access_to_password").css("display") == "none") {
        $('#admin_access_to_password').slideDown('slow');
        $(this).text('- Hide Pannel');
        $('#reset_password').val('1');
    } else {
        $('#admin_access_to_password').slideUp('slow');
        $('#reset_password').val('0');
        $(this).text('+ Change Password');

    }
});



$('#preview-img-ipad').bind('click', function() {
    $('.modal-footer .change_id').attr('id', 'confirm_ipad_crop');
    $('#myModalCrop .modal-header .modal-title').html("Crop Image For iPad");
});

$('#preview-img-iphone').bind('click', function() {
    $('.modal-footer .change_id').attr('id', 'confirm_iphone_crop');
    $('#myModalCrop .modal-header .modal-title').html("Crop Image For iPhone");
});

$('#preview-img-modal').imgAreaSelect({
    parent: '.modal-dialog-crop',
    onSelectEnd: function(img, selection) {
        $('#cropX').val(selection.x1);
        $('#cropY').val(selection.y1);
        $('#cropW').val(selection.width);
        $('#cropH').val(selection.height);

    }
});

$('.nav_cricles_div ul li').bind('click', function() {
    $('.hastohide').css('display', 'none');
});

$('#admin_user_listings').delegate('.imgDeleteUser','click', function() {
    $('#delete_user_id').val($(this).attr('data-id'));
    $('#myModal').modal('show')
});

$('#confirm_delete_user').bind('click', function() {
    var formData = "userId=" + $('#delete_user_id').val();
    if ($('#delete_user_id').val() != "") {
        $.ajax({
            type: 'POST',
            url: '/ajax/delete-user',
            data: formData
        }).done(function(resp) {
            location.reload();
        });
    }

    return false;
});

/*
$('#feedPopup').on('shown.bs.modal', function() {
    SharerPlayer.calcWidth();
   // $('#feed-popup-descBottom').mCustomScrollbar();
});

$('#feedPopup').on('hidden.bs.modal', function() {
  //  $('#feed-popup-descBottom').mCustomScrollbar("destroy")
})
*/

/* by anulal sreeranjanan for Oauth
	
		goog plus starts here 	*/


var OAUTHURL = 'https://accounts.google.com/o/oauth2/auth?';
var VALIDURL = 'https://www.googleapis.com/oauth2/v1/tokeninfo?access_token=';
var SCOPE = 'https://www.googleapis.com/auth/userinfo.profile';
var CLIENTID = '1002369178751-9q1fdf84tbdsa64t5dtrsnh254bgpdp5.apps.googleusercontent.com';
var REDIRECT = 'https://prod.thefutureisvast.us/cms/'
var TYPE = 'token';
var _url = OAUTHURL + 'scope=' + SCOPE + '&client_id=' + CLIENTID + '&redirect_uri=' + REDIRECT + '&response_type=' + TYPE;

function login() {
    var desc = $('#add_livestream').css('display') == 'block' ? $('#add_livestream #txtStory').val() : $('#step5 #txtStory').val();
    var title = $('#add_livestream').css('display') == 'block' ? 'Vast' : $('#inputtitle').val();
    var image = $('#add_livestream').css('display') == 'block' ? $('#add_livestream #secondary_image_post').attr('src') : $('#step5 .add-post-music-image').attr('src');
    $("meta[itemprop='name']").attr("content", title);
    $("meta[itemprop='description']").attr("content", desc);
    $("meta[itemprop='image']").attr("content", image);
    var win = window.open(_url, "windowname1", 'width=800, height=600');

    var pollTimer = window.setInterval(function() {
        try {
            if (win.document.URL.indexOf(REDIRECT) != -1) {
                window.clearInterval(pollTimer);
                var url = win.document.URL;
                acToken = gup(url, 'access_token');
                tokenType = gup(url, 'token_type');
                expiresIn = gup(url, 'expires_in');
                win.close();
                validateToken(acToken);
            }
        } catch (e) {}
    }, 100);
}

function validateToken(token) {
    $.ajax({
        url: VALIDURL + token,
        data: null,
        success: function(responseText) {
            getUserInfo(token);
        },
        dataType: "jsonp"
    });
}

function getUserInfo(token) {
    $.ajax({
        url: 'https://www.googleapis.com/oauth2/v1/userinfo?access_token=' + acToken,
        data: null,
        success: function(resp) {
            $.ajax({
                url: '/ajax/update-gplus',
                type: 'POST',
                data: {
                    id: resp.id,
                    token: token
                },
                success: function(responseText) {
                    if (responseText != '1') {
                        $('.share-det-image .google_plus').removeClass('active');
                    }
                }
            });
        },
        dataType: "jsonp"
    });
}




function gup(url, name) {
    var regX = name + "=([^&]+)";
    var res = url.match(regX)[1];
    if (res == null)
        return "";
    else
        return res;
}

/* google plus ends here*/


/* twitter starts here*/
Vast = {
    Login: function(loginType) {

        var desc = $('#add_livestream').css('display') == 'block' ? $('#add_livestream #txtStory').val() : $('#step5 #txtStory').val();
        var title = $('#add_livestream').css('display') == 'block' ? 'Vast' : $('#inputtitle').val();
        var image = $('#add_livestream').css('display') == 'block' ? $('#add_livestream #secondary_image_post').attr('src') : $('#step5 .add-post-music-image').attr('src');
        $("meta[name='twitter:title']").attr("content", title);
        $("meta[name='twitter:description']").attr("content", desc);
        $("meta[name='twitter:image:src']").attr("content", image);
        switch (loginType) {
            case 'TWITTER':
                Vast.openWindow('/cms/twitter/login', 'VAST TWITTER LOGIN', 500, 300);
                return false;
                break;
        }
    },
    openWindow: function(url, title, width, height) {
        var leftPosition, topPosition;
        leftPosition = (window.screen.width / 2) - ((width / 2) + 10); // Allow for borders.
        topPosition = (window.screen.height / 2) - ((height / 2) + 50); // Allow for title and status bars.

        // Open the window.
        window.open(url, title, "status=no,height=" + height + ",width=" + width + ",resizable=yes,left=" + leftPosition + ",top=" + topPosition + ",screenX=" + leftPosition + ",screenY=" + topPosition + ",toolbar=no,menubar=no,scrollbars=no,location=no,directories=no");
    },
    showAlert: function(msgType) {
        $('#alert-' + msgType).modal('show');
    }
};

function getTwitterData(result) {
    var res = $.parseJSON(result);
    $('.twitterToken').val(res.oauth_token);
    $('.twitterUserID').val(res.twitter_id);

}

function settingTwitterRefresh() {
        if (identifyTwitterClick == 1) {
            identifyTwitterClick = 0;
            location.reload();
        }
    }
    /* twitter ends here */


/* by anulal sreeranjanan for Oauth*/

var tumblrWin;

$('.share-det-image .th .t,.t').bind('click', function() {
    if ($(this).hasClass('tumblrSettings'))
        identifyTumblrClick = 1;
    if ($('.share-det-image .th .t,.edit-share-det-image .th .t').hasClass('active')) {
        $('.share-det-image .th .t,.edit-share-det-image .th .t').removeClass('active');
        $('#tumblr_share').val('0');

    } else {
        $('.share-det-image .th .t,.edit-share-det-image .th .t').addClass('active');
        $('#tumblr_share').val('1');

        if ($('#tumblr_token_exist').val() != '1') {
            $.ajax({
                    url: "/cms/tumblr/token-exist",
                    cache: false,
                    async: false
                })
                .done(function(msg) {
                    if (msg == 1) {
                        return false;
                    } else {
                        tumblrWin = window.open('/cms/tumblr/login', "", 'width=800, height=600');
                    }
                });
        }
    }



});
// On Close  tumblr oAuth window
function tumblrCallback() {
    $('.share-det-image .th .t,.t').addClass('active');
    $('#tumblr_share').val('1');
    tumblrWin.close();
}

function settingTumblrRefresh() {
    if (identifyTumblrClick == 1) {
        identifyTumblrClick = 0;
        location.reload();
    }
}

$('.share-det-image .google_plus .g,.g').bind('click', function() {
    if ($(this).hasClass('gplusSettings'))
        identifyGplusClick = 1;
    if ($('.share-det-image .google_plus .g').hasClass('active')) {
        $('.share-det-image .google_plus .g').removeClass('active');
        $('#gplus_share').val('0');

    } else {
        $('.share-det-image .google_plus .g').addClass('active');
        $('#gplus_share').val('1');
        login();
    }

});

$('.share-det-image .instagram .m').bind('click', function() {

    if ($('.share-det-image .instagram .m').hasClass('active')) {
        $('.share-det-image .instagram .m').removeClass('active');
        $('#instagram_share').val('0');

    } else {
        $('.share-det-image .instagram .m').addClass('active');
        $('#instagram_share').val('1');
    }

});
var fbWin;
$('.social-section .fb-login').bind('click', function() {
    //var fbUrl = "https://www.facebook.com/dialog/oauth?client_id=882717915118682&redirect_uri=https://prod.thefutureisvast.us/cms/fb&scope=publish_actions,publish_pages,manage_pages";
    //fbWin = window.open(fbUrl, 'Fb Login', "status=no,height=" + 600 + ",width=" + 800 + ",resizable=yes,toolbar=no,menubar=no,scrollbars=no,location=no,directories=no");
    
    var fbUrl = "https://www.facebook.com/dialog/oauth?client_id=420342051704757&redirect_uri=https://bug16.thefutureisvast.us/cms/fb&scope=publish_actions,publish_pages,manage_pages";
    fbWin = window.open(fbUrl, 'Fb Login', "status=no,height=" + 600 + ",width=" + 800 + ",resizable=yes,toolbar=no,menubar=no,scrollbars=no,location=no,directories=no");
});

$('.share-det-image .facebook #fb_login,.fb-login').bind('click', function() {
    if ($(this).hasClass('facebookSettings'))
        identifyFbClick = 1;
    if ($('.share-det-image .facebook #fb_login,.edit-share-det-image .facebook .fb-login').hasClass('active')) {
        $('.share-det-image .facebook #fb_login,.edit-share-det-image .facebook .fb-login').removeClass('active');
        $('#facebook_share').val('0');

    } else {
        $('.share-det-image .facebook #fb_login,.edit-share-det-image .facebook .fb-login').addClass('active');
        $('#facebook_share').val('1');

        if ($('#fb_token_exist').val()) {
            $.ajax({
                    url: "/cms/facebook/token-exist",
                    cache: false,
                    async: false
                })
                .done(function(msg) {
                    if (msg == 1) {

                        return false;
                    } else {
                        //var fbUrl = "https://www.facebook.com/dialog/oauth?client_id=882717915118682&redirect_uri=https://prod.thefutureisvast.us/cms/fb&scope=publish_actions,publish_pages,manage_pages";
                        //fbWin = window.open(fbUrl, 'Fb Login', "status=no,height=" + 600 + ",width=" + 800 + ",resizable=yes,toolbar=no,menubar=no,scrollbars=no,location=no,directories=no");
                        
                        var fbUrl = "https://www.facebook.com/dialog/oauth?client_id=420342051704757&redirect_uri=https://bug16.thefutureisvast.us/cms/fb&scope=publish_actions,publish_pages,manage_pages";
                        fbWin = window.open(fbUrl, 'Fb Login', "status=no,height=" + 600 + ",width=" + 800 + ",resizable=yes,toolbar=no,menubar=no,scrollbars=no,location=no,directories=no");
                    }
                });
        }
    }


});

function facebookCallback() {
    fbWin.close();
}

function settingFbRefresh(id) {
    $('#fb_page_data').val(id);
    if (identifyFbClick == 1) {
        identifyFbClick = 0;
        location.reload();
    }
}

$('.share-det-image .twiiter .twitter,.twitter').bind('click', function() {
    if ($(this).hasClass('twitterSettings'))
        identifyTwitterClick = 1;
    if ($('.share-det-image .twiiter .twitter,.edit-share-det-image .twiiter .twitter').hasClass('active')) {
        $('.share-det-image .twiiter .twitter,.edit-share-det-image .twitter').removeClass('active');
        $('#twiiter_share').val('0');

    } else {
        $('.share-det-image .twiiter .twitter,.edit-share-det-image .twiiter .twitter').addClass('active');
        $('#twiiter_share').val('1');

        if ($('#twitter_token_exist').val() != '1') {
            $.ajax({
                    url: "/cms/twitter/token-exist",
                    cache: false,
                    async: false
                })
                .done(function(msg) {
                    if (msg == 1) {
                        return false;
                    } else {
                        //tumblrWin = window.open('/public/tumblr/login', "", 'width=800, height=600');
                        Vast.Login('TWITTER');
                    }
                });
        }
    }
});




/* Super admin featured starts */

$('#channel_select').bind('change', function() {
    var formData = "chapterId=" + $(this).val();
    if ($('#channel_select').val() != "") {
        $.ajax({
            type: 'POST',
            url: '/ajax/get-chapters',
            data: formData,
            beforeSend: function() {
                $('#chapter_list_table').hide();
				$('#nothing_to_display').hide();
            },
        }).done(function(resp) {
			if(resp == ''){
				$('#nothing_to_display').html('No Chapters in this Channel to display!').show();
			}else{
				$('#nothing_to_display').hide();
				$('#chapter_list_table').show();
				$('#chapter_list_table tbody').html(resp);
			}

        });
    }
    return false;

});

$(document).on('click', '.featured_img', function() {
	
    var id = $(this).attr('id')
    var chapterText = $(this).parent().parent('td').prev('.chaptername_td').html();
	
    var status = $(this).parent('td').children('.featured_hidden').val();
    var active = $(this).parent().children('.active_hidden').val();
    status == 1 ? $('#radio_featured').prop('checked', true) : $('#radio_nonfeatured').prop('checked', true);
    active == 0 ? $('#radio_active').prop('checked', true) : $('#radio_inactive').prop('checked', true);
    $('#chapter_id').val(id);
    $('#chapter_name').val(chapterText);
    $('#featuredModal').modal('show');
});



$(document).on('click', '#confirm_change_featured', function() {

    var status = $('input[name=featured_radio]:checked', '#featuredModal').val();
    var id = $('#chapter_id').val();
    var name = $('#chapter_name').val();
    var active = $('input[name=active_radio]:checked', '#featuredModal').val();
    var formData = new FormData();
    formData.append('chapterId', id);
    formData.append('chapter_name', name);
    formData.append('status', status);
    formData.append('active', active);
    if (id != "") {
        $.ajax({
            type: 'POST',
            url: '/ajax/set-chapter-status',
            data: formData,
            processData: false,
            contentType: false,
        }).done(function(resp) {
            if (resp == 1) {
                $('#featured_hidden_' + id).val(status);
                $('#active_hidden_' + id).val(active);
                $('#chapter_list_table #chapter_td_' + id).html(name);
                //status = status == 0 ? "<span class='non_featured_span'>Non Featured</span>" : "<span class='featured_span'>Featured</span>";
                active = active == 0 ? "<span class='featured_span'>Active</span>" : "<span class='in_active_span'>Inactive</span>";
               // $('#featured_span_' + id).html(status);
                $('#active_span_' + id).html(active);
                $('.alert').css('display', 'none');
                $('.alert-success').css('display', 'block');

            } else {
                $('.alert').css('display', 'none');
                $('.alert-danger').css('display', 'block');
            }
        });
    }
    return false;
});
/* Super admin featured ends */

/* super admin channel status starts here */
$(document).on('click', '#confirm_change_channel_status', function() {
    var status = $('input[name=featured_radio]:checked', '#featuredModal').val();
    var id = $('#chapter_id').val();
    var formData = new FormData();
    formData.append('channelId', id);
    formData.append('status', status);
    if (id != "") {
        $.ajax({
            type: 'POST',
            url: '/ajax/set-channel-status',
            data: formData,
            processData: false,
            contentType: false,
        }).done(function(resp) {
            if (resp == 1) {
                $('#featured_hidden_' + id).val(status);
                status = status == 1 ? "<span class='non_featured_span non_active_span'>Inactive</span>" : "<span class='featured_span'>Active</span>";
                $('#featured_span_' + id).html(status);
                $('.alert').css('display', 'none');
                $('.alert-success').css('display', 'block');

            } else {
                $('.alert').css('display', 'none');
                $('.alert-danger').css('display', 'block');
            }
        });
    }
    return false;
});

$(document).on('click', '.channel_featured_img', function() {
    var id = $(this).attr('id')
    var channelText = $(this).parent('td').prev('.chaptername_td').html();
    var status = $(this).parent('td').children('.featured_hidden').val();
    status == 0 ? $('#radio_featured').prop('checked', true) : $('#radio_nonfeatured').prop('checked', true);
    $('#chapter_id').val(id);
    $('#chapterName').html(channelText);
    $('#featuredModal').modal('show');
});

$(document).on('click', '.channel_delete', function() {
    var channelText = $(this).parent('td').prev('.chaptername_td').html();
    $('#channel_name').html(channelText);
    $('#chapter_id_delete').val($(this).data("id"));
    $('#channelDeleteModal').modal('show');

});

$(document).on('click', '#confirm_delete_channel', function() {
    var channel_id = $('#chapter_id_delete').val();
    var formData = new FormData();
    formData.append('channelId', channel_id);
    if (channel_id != "") {
        $.ajax({
            type: 'POST',
            url: '/ajax/delete-channel-details',
            data: formData,
            processData: false,
            contentType: false,
        }).done(function(resp) {
            if (resp != 0) {
                $('.alert').css('display', 'none');
                $('.alert-delete-success').css('display', 'block');
                location.reload();

            } else {
                $('.alert').css('display', 'none');
                $('.alert-delete-danger').css('display', 'block');
            }
        });
    }
    return false;
});
/* super admin channel status ends here */

/* super admin chapter delete start here */

$(document).on('click', '.chapter_delete', function() {
    var chapterText = $(this).parent('td').prev('.chaptername_td').html();
    $('#chapter_name').html(chapterText);
    $('#chapter_id_delete').val($(this).data("id"));
    $('#chapterDeleteModal').modal('show');

});

$(document).on('click', '#confirm_delete_chapter', function() {
    var chapter_id = $('#chapter_id_delete').val();
    var formData = new FormData();
    formData.append('chapterId', chapter_id);
    if (chapter_id != "") {
        $.ajax({
            type: 'POST',
            url: '/ajax/delete-chapter-details',
            data: formData,
            processData: false,
            contentType: false,
        }).done(function(resp) {
            if (resp) {
                $('.alert').css('display', 'none');
                $('.alert-delete-success').css('display', 'block');
                location.reload();

            } else {
                $('.alert').css('display', 'none');
                $('.alert-delete-danger').css('display', 'block');
            }
        });
    }
    return false;
});

/* super admin chapter delete ends here */

$('#cart_btn_icon').bind('click', function() {
    $(".feed-popup-player, .secondary-content").hide();
    $('#cart_icon_btn_poper').slideToggle().toggleClass('opened');
	$('#cart_icon_btn_poper').is( ".opened" ) == true ? $('#cart_btn_icon').addClass('active') : $('#cart_btn_icon').removeClass('active');
    if ($('#popupVideoId').length > 0)
        $('#popupVideoId').trigger("pause");
    if ($('#popupAudioId').length > 0)
        $('#popupAudioId').trigger("pause");
    var elemId = $('.popupMediaClass').attr('id');
    if ($('#' + elemId).length > 0)
        $('#' + elemId).trigger("pause");
});

$('.toggle-elements').bind('click', function() {
    var displayValue = $('.content-toggle-class').css('display');
    if (displayValue == 'none') {
        $('.content-toggle-class').css('display', 'block');
		$('#content_pop_toggler').addClass('active');
    } else {
        $('.content-toggle-class').css('display', 'none');
		$('#content_pop_toggler').removeClass('active');
    }
});

$('#addChapterAnchor').bind('click', function() {
    $('#section-add-chapter-new').toggle('slow');
});


/* for add post secondary  audio file upload & duration  claculation start */
/*
$("#secondaryAudioHidden").on("loadedmetadata", function(e) {
    var seconds = e.currentTarget.duration;
    var duration = moment.duration(seconds, "seconds");
    var time = "";
    var hours = duration.hours();
    var second = "";
    if (hours > 0) {
        time = hours + ":";
    }
    if (duration.seconds() < 10)
        second = "0" + duration.seconds();
    else
        second = duration.seconds();

    time = time + duration.minutes() + ":" + second;
    $("#audio-duration").text(time);

}); */


$("#loaded_second_audio_item_remove").bind('click', function() {
    deleteUploadedItemFromS3('', 'music', 'audios', $('#second_audio').val());
    $('#second_audio').val('');
    $('#loaded_sec_audio_item').hide();
	$('#secondaryAudioPlayer').trigger("pause");
	$('#secondaryAudioPlayer').prop("currentTime",0);
    $('#btn_secondaryAudioUploadImage').show();
});

$('#btn_secondaryAudioEditUpload').bind('click',function(){
	deleteUploadedItemFromS3('', 'music', 'audios', $('#second_audio').val());
    $('#second_audio').val('');
	$('#secondaryAudioPlayer').trigger("pause");
	$('#secondaryAudioPlayer').prop("currentTime",0);
})



$("#loaded_second_video_item_remove").bind('click', function() {
    deleteUploadedItemFromS3('', 'video', 'secvideo', $('#second_video').val());
    $('#second_video').val('');
    $('#loaded_sec_video_item').hide();
    $("#video-duration").text('Uploaded');
    $('#btn_secondaryVideoUploadImage').show();
});

$("#loaded_second_video_item_edit").bind('click', function() {
    deleteUploadedItemFromS3('', 'video', 'secvideo', $('#second_video').val());
    $('#second_video').val('');
});

/* for add post secondary  video file upload & duration claculation end */


$("#loaded_second_image_item_remove").bind('click', function() {
    deleteUploadedItemFromS3('', 'image', '', $('#second_image').val());
    $('#second_image').attr('value', '');
    $("#image-details").text('Uploaded');
    $('#loaded_sec_image_item').hide();
    $('#btn_secondaryImageUploadImage').show();

});


$("#loaded_second_cover_item_remove").bind('click', function() {
    deleteUploadedItemFromS3('', 'image', '', $('#second_video_thumb').val());
    $('#second_video_thumb').attr('value', '');
	$('#addthumb_preview').attr('src','');
    $("#coverimage").text('Uploaded');
    $('#loaded_sec_cover_item').hide();
	$('#sec-video-thumb-button').addClass('sec-video-thumb-button');
    $('#btn_secondaryCoverUploadImage').show();
});


$('#loaded_message_image_item_remove').bind('click', function() {
    $('#message_image').attr('value', '');
    $('#loaded_message_image_item').hide();
    $('#btn_messageImageUpload').show();
});
/* for message image upload ends */

/* text color change on checked radio button on share starts */
$('.share-publish-section .publish-radio').bind('click', function() {
    if ($(this).is(':checked')) {
        $('.share-publish-section .publish-radio-msgRadioText').css('color', '#fff');
        $(this).parent().find('.msgRadioText').css('color', '#fff');
        if ($(this).val() == 1) {
            $('#schedule_content_div').slideDown('slow');
        } else {
            $('#schedule_content_div').slideUp('slow');
        }
    }
});

$('.share-publish-section .user-radio').bind('click', function() {
    if ($(this).is(':checked')) {
        $('.share-publish-section .user-radio-msgRadioText').css('color', '#fff');
        $(this).parent().find('.msgRadioText').css('color', '#fff');
    }
});


/* text color change on checked radio button on share ends */

/* placeholder show and fade on cursor pointer starts */
$('input,textarea').focus(function() {
    $(this).data('placeholder', $(this).attr('placeholder'))
    $(this).attr('placeholder', '');
});
$('input,textarea').blur(function() {
    $(this).attr('placeholder', $(this).data('placeholder'));
});

/* placeholder show and fade on cursor pointer ends */



//delete uploaded item from amazon s3 starts here

function deleteUploadedItemFromS3(postId, fileType, folderName, fileName) {
        var dataString = 'postId=' + postId + '&fileType=' + fileType + '&fileName=' + fileName + '&folderName=' + folderName;
        $.ajax({
            type: 'POST',
            url: "/ajax/delete-uploaded-item",
            data: dataString
        }).done(function() {});
        return false;

    }
    //delete uploaded item from amazon s3 ends here

//delete uploaded videos from amazon s3 starts here

function deleteUploadedVideosFromS3(folderName, fileName) {
        var dataString = 'fileName=' + fileName + '&folderName=' + folderName;
        $.ajax({
            type: 'POST',
            url: "/ajax/delete-uploaded-videos",
            data: dataString
        }).done(function() {});
        return false;

    }
    //delete uploaded videos from amazon s3 ends here

$(document).ready(function() {
   




}); /* document ready fn ends here */

/* previous POI selection display starts here */
$(window).load(function() {
	if($('#secondary_image_post').length > 0){
		var poix = $('#secondary_image_post').attr('data-poix');
		var poiy = $('#secondary_image_post').attr('data-poiy');
		var left = parseInt(poix * $('#secondary_image_post').width());
		var top = parseInt(poiy * $('#secondary_image_post').height());
		$('.poi_position').css('margin-top', top + 'px');
		$('.poi_position').css('margin-left', left + 'px');
		$('.poi_position').show();
	}
});
/* previous POI selection display ends here */

function randombetween(min, max) {
    return Math.floor(Math.random() * (max - min + 1) + min);
}

function isPhone() {
    return (
        (navigator.platform.indexOf("iPhone") != -1) ||
        (navigator.platform.indexOf("iPod") != -1)
    );
}

$('#subscriber2').bind('click', function() {
    if ($('#subscription_id').val() == "") {
        $('#subscriptionMessage').modal('show');
        $('#subscriber1').trigger('click');
        $('.enable_color2').css('color', '#868686');
        $('.enable_color1').css('color', '#da3838');
    }
});

$('#msg-users1').bind('click', function() {
    if ($('#subscription_id').val() == "") {
        $('#subscriptionMessage').modal('show');
        $(this).attr('checked', false);
        $('.msgRadioText').css('color', '#868686');
    }
});

$('#edit_page_scedule1').bind('click', function() {
    $('#editpage_datetime_container').hide();
    $('.schedule-text').css('color', '#868686');
    $('.publishnow-text').css('color', '#da3838');
});

$('#edit_page_scedule2').bind('click', function() {
    $('#editpage_datetime_container').show();
    $('.publishnow-text').css('color', '#868686');
    $('.schedule-text').css('color', '#da3838');
});


/* super admin manage explore change image on selecting artists starts here */
$('.manage-explore-select-artist').bind('change', function() {
    // type 1 is for artist based 0 for chapter based
    var getClass = "cell-" + $(this).attr('id');
    var dataString = 'type=1&id=' + $(this).val();
    if ($(this).val() == 0) {
        var no = $(this).attr('id').split('-');
        $('.' + getClass).html('<span class="manage-explore-number">' + no[1] + '</span>');
    } else {
        $.ajax({
            type: 'POST',
            url: "/ajax/get-last-post-image",
            data: dataString
        }).done(function(url) {
            if (url != "") {
                var data = "<img src='" + url + "' class='manage_explore_channel_preview'  />";
                $('.' + getClass).html(data);
            }
        });
    }
    return false;

});

/* super admin manage explore change image on selecting artists ends  here */

/* super admin manage explore add chapter starts here */
$('#manage-explore-add-chapter').bind('click', function() {
    var selectId = '#manage-explore-add-new-chapter-select';
    if ($(selectId).val() != "") {
        var numItems = $('#manage-explore-featured-list-container .manage-explore-featured-list-item').length;
        if (numItems < 5) {
            var dataString = 'items=' + numItems + '&id=' + $(selectId).val();
            $.ajax({
                type: 'POST',
                url: "/ajax/manage-explore-add-new-chapter",
                data: dataString
            }).done(function(data) {
                var dataUrl = 'type=0&id=' + $(selectId).val();
                $.ajax({
                    type: 'POST',
                    url: "/ajax/get-last-post-image",
                    data: dataUrl
                }).done(function(imgUrl) {
                    if (imgUrl != "") {
                        $('.manage-explore-ipad-main').html("<img src='" + imgUrl + "' class='img-rounded' width='118' height='118' />");
                        $('.manage-explore-iphone-main').html("<img src='" + imgUrl + "' class='img-rounded' width='118' height='57' />");
                    }
                });
                if (data != "") {
                    $('#manage-explore-featured-list-container').append(data);
                    $(selectId).val('');
                }
            });
        }
    }
    return false;
});
/* super admin manage explore add chapter ends here */


/* super admin manage explore delete  chapter starts here */
$('#manage-explore-featured-list-container').delegate('.manage-explore-delete-list-item', 'click', function() {
    $(this).parent().parent('.manage-explore-featured-list-item').remove();
    var val = $("div .manage-explore-featured-list-item:last").find('.manage-explore-add-chapter-select-div select').val();
    if (val > 0) {
        var dataUrl = 'type=0&id=' + val;
        $.ajax({
            type: 'POST',
            url: "/ajax/get-last-post-image",
            data: dataUrl
        }).done(function(imgUrl) {
            if (imgUrl != "") {
                $('.manage-explore-ipad-main').html("<img src='" + imgUrl + "' class='img-rounded' width='118' height='118' />");
                $('.manage-explore-iphone-main').html("<img src='" + imgUrl + "' class='img-rounded' width='118' height='57' />");
            }
        });
    } else {
        $('.manage-explore-ipad-main').html('<span class="manage-explore-number">1</span>');
        $('.manage-explore-iphone-main').html('<span class="manage-explore-number">1</span>');
    }

});
/* super admin manage explore add chapter ends here */

$('.btnManageExplore').bind('click', function() {
    var dataString = $('#superadmin_manageexplore').serialize();
	
    $.ajax({
        type: 'POST',
        url: "/ajax/superadmin-manage-explore-save",
        data: dataString + '&type=' + add_cover_type,
    }).done(function(data) {
		location.href = "/cms/superadmin/list-channelgroups";
		/*if(add_cover_type == 0)	
        	location.href = "/cms/superadmin/list-channelgroups";
		else
			location.href = "/cms/superadmin/managechapter/"+$('#networkdropdown').val()+"/list";
		*/
    });

});

/* Twitter account unlink starts */

$('.tw-unlink').bind('click', function() {
    var channel_id = $('#channelId').val();
    $.ajax({
        type: 'POST',
        url: "/ajax/twitter/unlink",
        data: 'channel_id=' + channel_id
    }).done(function(data) {
        location.reload();
    });
});

/* Twitter account unlink ends */

/* FB account unlink starts */

$('.fb-unlink').bind('click', function() {
    var channel_id = $('#channelId').val();
    $.ajax({
        type: 'POST',
        url: "/ajax/facebook/unlink",
        data: 'channel_id=' + channel_id
    }).done(function(data) {
        location.reload();
    });
});

/* FB account unlink ends */

/* Tumblr account unlink starts */

$('.tumblr-unlink').bind('click', function() {
    var channel_id = $('#channelId').val();
    $.ajax({
        type: 'POST',
        url: "/ajax/tumblr/unlink",
        data: 'channel_id=' + channel_id
    }).done(function(data) {
        location.reload();
    });
});

/* Tumblr account unlink ends */


/* edit channel group starts here */
$('#channel_list_table').delegate('.channel_group_edit', 'click', function() {
    var id = $(this).attr('data-id');
    if (id != "")
        $.ajax({
            type: 'POST',
            url: "/ajax/get-channel-group-details",
            data: 'id=' + id
        }).done(function(data) {
            var parsedJson = jQuery.parseJSON(data);
            $('#channelgroup_text').val(parsedJson.name);
            $('#channelgroup-cover-text').html(parsedJson.cover);
            $('#channelgroup_cover_image').val(parsedJson.cover);
            $('#channelgroup_id').val(id);
            $('#upload_channelgroup_cover_button').hide();
            $('#uploaded_channelgroup_cover_button').show();
            $('#add_channel_group_container').slideDown('slow');
            window.scrollTo(10, 10);
        });
    return false;
});
/* edit channel group ends here */

/* delete channel group starts here */
$('.group_list').delegate('.channel_group_delete', 'click', function() {
    $('#delete_channel_group_id').val($(this).attr('data-id'));
    $('#myModal').modal('show');
});

$('#confirm_delete_channel_group').bind('click', function() {
    var id = $('#delete_channel_group_id').val();
    if (id != "") {
        $.ajax({
            type: 'POST',
            url: '/ajax/delete-channel-group',
            data: 'id=' + id
        }).done(function(resp) {
            location.reload();
            $('#myModal').modal('hide');
        });
    }
    return false;
});
/* delete channel group ends here */

$('#img_postfeatured').bind('click', function() {
    if ($(this).hasClass('bluredClass'))
        $(this).removeClass('bluredClass');
    var featured_post = $('#featured_post').val() == 0 ? 1 : 0;
    $('#featured_post').val(featured_post);
    var featured_class = featured_post == 0 ? 'bluredClass' : '';
    $(this).addClass(featured_class);

});

/* livestream deatils save starts here */
$('#livestreamSave').bind('click', function(e) {
    e.preventDefault();
    var paid = $('input[name=msg-users]:checked', '#add-post-page form').val();
    var formData = 'livestream_name=' + $('#livestreamname').val() + '&livestream_title=' + $('#livestreamtitle').val() + '&livestream_event=' + $('#livestreamevent').val() +'&photo=' + $('#live_stream_image').val() + '&live_thumb_width=' + $('#live_thumb_width').val() + '&live_thumb_height=' + $('#live_thumb_height').val() + '&live_hdthumb_width=' + $('#live_hdthumb_width').val() + '&live_hdthumb_height=' + $('#live_hdthumb_height').val() + '&live_sd_width=' + $('#live_sd_width').val() + '&live_sd_height=' + $('#live_sd_height').val() + '&subscription=' + paid;

    $.ajax({
        type: 'POST',
        url: '/ajax/update-livestream-data',
        data: formData
    }).done(function(resp) {
        if (resp == 1)
            location.href = "/cms/feeds";
    });

});
/* livestream deatils save ends here */


/* new scripts anulal sreeranjanan */
$(document).ready(function() {


    if ($('#cover_type').length > 0) {
        $('#cover_type button').bind('click', function() {
            if ($(this).attr('id') == 'btnNetworkCover') {
                add_cover_type = 0;
                $('#add_story,#add_channel').hide();
                $('#add_cover').show();
            } else if ($(this).attr('id') == 'btnChannelCover') {
                add_cover_type = 1;
                $('#add_cover,#add_story').hide();
                $('#add_channel').show();
            } else if ($(this).attr('id') == 'btnStoryCover') {
                add_cover_type = 2;
                $('#add_cover,#add_channel').hide();
                $('#add_story').show();
            }
            $('#step0').hide();
            $('#step1').show();
            return false;
        });
    }
	
	 $('.nav_cricles_admin_div').delegate('.nav_visited', 'click', function() {
			var step = parseInt($(this).text())-1;
            $('#step' + step).show().nextAll('section').hide();
     });
	 
	 $('.nav_cricles_div').delegate('.nav_visited', 'click', function() {
            $('#step' + $(this).text()).show().nextAll('section').hide();
      });
});
$('#cover_next_btn').bind('click', function(e) {
    e.preventDefault();
    if (($('#networkname').val().length > 2) && ($('#channelgroup_cover_image').val() != "")) {
        var nextStep = $(this).parents('section').next('section').attr("id");
        $.ajax({
            type: 'POST',
            url: '/ajax/superadmin-check-screenname',
            data: 'screenname=' + $('#networkname').val()
        }).done(function(resp) {
            if (resp == 1) {
                $('#errorMsg').html('Network Name Already Exists!');
                $('#errorMsg').addClass('alert alert-danger');
            } else {
                $('#errorMsg').removeClass('alert alert-danger');
                $('#errorMsg').html('');
                $('section').hide();
                $('#build_channel').hide();
                $('#build_network').show();
                $('#' + nextStep).show();
            }
        });
        return false;
    }

});

$('#manageNetworkPopup').find('.modal-body #alpabetic-selector span').on('click', function() {
    var alpha = $(this).text();
    alpha = alpha == "#" ? '' : alpha;
    $.ajax({
        type: 'POST',
        url: '/ajax/get-channel-list-alphabatically',
        data: 'alpha=' + alpha
    }).done(function(resp) {
        $('#artists_selector').html(resp);
    });

});


$('#manageNetworkPopup').find('#next-choose-chapter').on('click', function() {
    var id = $('#selected_alpha').val();
    if (id != "")
        $.ajax({
            type: 'POST',
            url: '/ajax/get-chapter-story-list',
            data: 'id=' + id
        }).done(function(resp) {
            $('#story_selector').html(resp);
            $('#pickartist').slideUp('slow');
            $('#choosechapter').slideDown('slow');
        });
    return false;
})

/*popup for featured chapters in network cover starts here */
$('.featured-item-containers').delegate('.add-button-icon .enable_popup', 'click', function() {
    $('#clicked_container').val($(this).parent().parent().attr('id'));
    $('#pickartist').show();
    $('#choosechapter').hide();
    $('#manageNetworkPopup').modal('show');
});
/*popup for featured chapters in network cover ends here */

$('#artists_selector').delegate('.artists', 'click', function() {
    $('.artists').removeClass('active-artist');
    $(this).addClass('active-artist');
    $('#selected_alpha').val($(this).attr('id'));
});

function sliderContent(id) {
    if ('open-drp-' + id == $('.me-chapter-content-active').attr('id')) {
        $('#open-drp-' + id).slideUp('slow');
        $('#head-drp-' + id).css('background', 'none');
        $('#drp-' + id).attr('src', '/images/me-button-down.png');
        $('.me-chapter-content').removeClass('me-chapter-content-active');
    } else {
        $('.me-chapter-content').slideUp('slow');
        $('.me-chapter-head').css('background', 'none');
        $('#head-drp-' + id).css('background-color', '#5b5b5b');
        $('.me-chapter-head-drop img').attr('src', '/images/me-button-down.png');
        $('#drp-' + id).attr('src', '/images/me-button-up.png');
        $('#open-drp-' + id).slideDown('slow');
        $('.me-chapter-content').removeClass('me-chapter-content-active');
        $('#drp-' + id).attr('src', '/images/me-button-up.png');
        $('#open-drp-' + id).addClass('me-chapter-content-active');
        if ($('.me-chapter-content-active').html() == "") {
            $('.me-chapter-content').slideUp('slow');
            $('#head-drp-' + id).css('background', 'none');
            $('#drp-' + id).attr('src', '/images/me-button-down.png');
            $('.me-chapter-content').removeClass('me-chapter-content-active');
        }
    }

}

function sliderContentChapter(id) {
    if ('open-drp-cptr-' + id == $('.me-chapter-content-active').attr('id')) {
        $('#open-drp-cptr-' + id).slideUp('slow');
        $('#head-drp-cptr-' + id).css('background', 'none');
        $('#drp-cptr-' + id).attr('src', '/images/me-button-down.png');
        $('.me-chapter-content').removeClass('me-chapter-content-active');
    } else {
        $('.me-chapter-content').slideUp('slow');
        $('.me-chapter-head').css('background', 'none');
        $('#head-drp-cptr-' + id).css('background-color', '#5b5b5b');
        $('.me-chapter-head-drop img').attr('src', '/images/me-button-down.png');
        $('#drp-cptr-' + id).attr('src', '/images/me-button-up.png');
        $('#open-drp-cptr-' + id).slideDown('slow');
        $('.me-chapter-content').removeClass('me-chapter-content-active');
        $('#drp-cptr-' + id).attr('src', '/images/me-button-up.png');
        $('#open-drp-cptr-' + id).addClass('me-chapter-content-active');
        if ($('.me-chapter-content-active').html() == "") {
            $('.me-chapter-content').slideUp('slow');
            $('#head-drp-cptr-' + id).css('background', 'none');
            $('#drp-cptr' + id).attr('src', '/images/me-button-down.png');
            $('.me-chapter-content').removeClass('me-chapter-content-active');
        }
    }

}

function storyImageSelector(id) {

    $('.me-chapter-post-images-inner').removeClass('video-overlay-active music-overlay-active event-overlay-active no-overlay-active');
    var parentClass = "";
    if ($('#storyImageSelector_' + id).parent('.me-chapter-post-images-inner').hasClass('video-overlay'))
        parentClass = "video-overlay-active";
    else if ($('#storyImageSelector_' + id).parent('.me-chapter-post-images-inner').hasClass('music-overlay'))
        parentClass = "music-overlay-active";
	else if ($('#storyImageSelector_' + id).parent('.me-chapter-post-images-inner').hasClass('event-overlay'))
        parentClass = "event-overlay-active";
    else if ($('#storyImageSelector_' + id).parent('.me-chapter-post-images-inner').hasClass('no-overlay'))
        parentClass = "no-overlay-active";
    $('#storyImageSelector_' + id).parent('.me-chapter-post-images-inner').addClass(parentClass);
	$('#selectedPost').val($('#storyImageSelector_' + id).attr('data-id'));
    $('#selectedImageUrl').val($('#storyImageSelector_' + id).attr('data-url'));
	$('#confirmed_post_id').val($('#storyImageSelector_' + id).attr('data-id'));
    $('#confirmed_image_url').val($('#storyImageSelector_' + id).attr('data-url'));
}

function storyImageSelectorCptr(id) {
    var chapters = $("input[name='artist[]']").map(function(){return $(this).val();}).get();
    if($.inArray($('#storyImageSelectorCptr_' + id).attr('data-chapter'),chapters) >=  0 && $('#manage-small-' +$('#popuptype').val()).children('.hiddenpost').val() != $('#storyImageSelectorCptr_' + id).attr('data-chapter')){
        alert('Chapter already selected.Please select another');
        return false;
    }
    $('.me-chapter-post-images-inner').removeClass('video-overlay-active music-overlay-active event-overlay-active no-overlay-active');
    var parentClass = "";
    if ($('#storyImageSelectorCptr_' + id).parent('.me-chapter-post-images-inner').hasClass('video-overlay'))
        parentClass = "video-overlay-active";
    else if ($('#storyImageSelectorCptr_' + id).parent('.me-chapter-post-images-inner').hasClass('music-overlay'))
        parentClass = "music-overlay-active";
	else if ($('#storyImageSelectorCptr_' + id).parent('.me-chapter-post-images-inner').hasClass('event-overlay'))
        parentClass = "event-overlay-active";
    else if ($('#storyImageSelectorCptr_' + id).parent('.me-chapter-post-images-inner').hasClass('no-overlay'))
        parentClass = "no-overlay-active";
    $('#storyImageSelectorCptr_' + id).parent('.me-chapter-post-images-inner').addClass(parentClass);
	$('#selectedPost').val($('#storyImageSelectorCptr_' + id).attr('data-id'));
    $('#selectedImageUrl').val($('#storyImageSelectorCptr_' + id).attr('data-url'));
	$('#confirmed_post_id').val($('#storyImageSelectorCptr_' + id).attr('data-id'));
    $('#confirmed_image_url').val($('#storyImageSelectorCptr_' + id).attr('data-url'));
}


$('#btnSelectChapterStory').bind('click', function() {
    var selectedContainer = $('#clicked_container').val();
    $('#input-' + selectedContainer).val($('#selectedPost').val());
    var width = $('#' + selectedContainer).width() + 'px';
    var height = $('#' + selectedContainer).height() + 'px';
    $('#' + selectedContainer).html('<div class="selectedImageEditsContainer"><img class="selectedImageEdit" data-id = "' + $('#selectedPost').val() + '" src="/images/btn-edit-icons.png" /><img class="selectedImageDelete" data-id = "' + $('#selectedPost').val() + '" src="/images/btn-delete-icons.png" /></div><img src="' + $('#selectedImageUrl').val() + '" class="img-rounded img-response" width="' + width + '" style="float:left;width:100%;" />');
});



/* new scripts anulal sreeranjanan */

/* Story cover change options start */
$('#cmbStoryCoverChannel').change(function() {
    var id = $('#cmbStoryCoverChannel').val();
    $.ajax({
        type: 'POST',
        url: '/ajax/get-channel-chapter-list',
        data: 'id=' + id
    }).done(function(resp) {
        $('#cmbStoryCoverChapter').html(resp);
    });
});

$('#cmbStoryCoverChapter').change(function() {
    var id = $('#cmbStoryCoverChapter').val();
    $.ajax({
        type: 'POST',
        url: '/ajax/get-chapter-storylist',
        data: 'id=' + id
    }).done(function(resp) {
        $('#cmbStoryCover').html(resp);
    });
});
/* Story cover change options end */
$('.featured-item-containers').delegate('.selectedImageEditsContainer .selectedImageDelete', 'click', function() {
    if (add_cover_type == 0)
        var innrHtml = '<div class="add-button-icon"><img src="/images/add-button-icon.png" class="enable_popup" alt=""></div>';
    else if (add_cover_type == 1)
        var innrHtml = '<div class="add-button-icon"><img src="/images/add-button-icon.png" class="enable_chapter_popup" alt=""></div>';
    var parentItem = 'input-' + $(this).parent().parent('.featured-item-containers').attr('id');
    $('#' + parentItem).val('0');
    $(this).parent().parent('.featured-item-containers').html(innrHtml);
});

$('.featured-item-containers').delegate('.selectedImageEditsContainer .selectedImageEdit', 'click', function() {
    if (add_cover_type == 0) {
        $('#clicked_container').val($(this).parent().parent().attr('id'));
        $('#pickartist').show();
        $('#choosechapter').hide();
        $('#manageNetworkPopup').modal('show');
    } else if (add_cover_type == 1) {
        $('#clicked_chapter_container').val($(this).parent().parent().attr('id'));
        $('#manageChapterPopup').modal('show');
    }
});

$('#chapter-container').delegate('.manage-network-small-container .delete-non-featured-edit-section', 'click', function() {
    var dataid = $(this).parent().attr('data-id');
    if (dataid == 0 || dataid > 8)
        $(this).parent().remove();
    else {
        $(this).parent().html('<span class="manage-explore-number">' + dataid + '</span><input type="hidden" name="artist[]" value="0" />');
    }
    return false;

});

/* Story cover add start */
$('#btnManageStoryCover').bind('click', function() {
    var formData = 'featured_chapter=' + $('#cmbStoryCover').val() + '&artist=' + $('#cmbStoryCoverChannel').val() + '&channelgroup_cover_image=' + $('#story_cover_image').val() + '&type=2&retinaH=' + $('#retinaH').val() + '&retinaW=' + $('#retinaW').val() + '&nonretinaH=' + $('#nonretinaH').val() + '&nonretinaW=' + $('#nonretinaW').val() + '&sdH=' + $('#sdH').val() + '&sdW=' + $('#sdW').val();
    $.ajax({
        type: 'POST',
        url: "/ajax/superadmin-manage-story",
        data: formData + '&networkname=' + $('#cmbStoryCover option:selected').text()
    }).done(function(data) {
		location.href = "/cms/superadmin/list-channelgroups";
    });

});
/* Story cover add end */

$('#chapter-container').delegate('.manage-network-small-container span,#edit-manage-explore-add-button', 'click', function() {
    $('#popuptype').val($(this).parent().attr('data-id'));
    $('#manageNetworkPopup').modal('show');
});

$('#confirm-channel-selection').bind('click', function() {
    var id = $('#selected_alpha').val();
	var chArray = [];
	$('input[name="artist[]"]').each(function() {
   		 chArray.push( $(this).val() );
	});
	if($.inArray(id, chArray)>= 0){
		$('#manageNetworkPopup').modal('hide');
		return false;
	}
    var popuptype = $('#popuptype').val();
    if (id != "")
        $.ajax({
            type: 'POST',
           // url: '/ajax/superadmin-manageexplore-getlastpostimage-channel',
		    url: '/ajax/superadmin-manageexplore-get-toc-image',
            data: 'id=' + id + '&type=' + popuptype
        }).done(function(resp) {
            if (popuptype == 0)
                $('#edit-manage-explore-add-button').before(resp);
            else
                $('#manage-small-' + popuptype).html(resp);
            $('#manageNetworkPopup').modal('hide');
        });
    return false;
});



$('#confirm-channel-selection-on-network').bind('click', function() {
    var id = $('#selected_alpha').val();
	var chArray = [];
	$('input[name="artist[]"]').each(function() {
   		 chArray.push( $(this).val() );
	});
	if($.inArray(id, chArray)>= 0){
		$('#manageNetworkPopup').modal('hide');
		return false;
	}
    var popuptype = $('#popuptype').val();
    if (id != "")
        $.ajax({
            type: 'POST',
           // url: '/ajax/superadmin-manageexplore-getlastpostimage-channel',
		    url: '/ajax/superadmin-manageexplore-get-toc-image',
            data: 'id=' + id + '&type=2' //2 for full sized images
		}).done(function(resp) {
            if (popuptype == 0){
               
				$('#chapter-container-sortable').append(resp);
            }else
                $('#manage-small-' + popuptype).html(resp);
            $('#manageNetworkPopup').modal('hide');
			$("#chapter-container-sortable").sortable();
			$("#chapter-container-sortable").disableSelection();
        });
    return false;
});

$('#btnEditManageExplore').bind('click', function() {
    $('#superadmin_edit_network_manageexplore').submit();
});

$('#channel_cover_next_btn').bind('click', function(e) {
    e.preventDefault();
    var nextStep = $(this).parents('section').next('section').attr("id");

    if (($('#channeldropdown').val() != "") && ($('#channel_cover_image').val() != "")) {
        $.ajax({
            type: 'POST',
            url: '/ajax/superadmin-getartistchapter',
            data: 'id=' + $('#channeldropdown').val()
        }).done(function(data) {
            var parsedJson = jQuery.parseJSON(data);
            $('.manage-explore-select-channel').html(parsedJson.str);
            $('#chapter-details-content').html(parsedJson.chapterStr);

        });
        var nextStep = $(this).parents('section').next('section').attr("id");
        $('section').hide();
        $('#build_network').hide();
        $('#build_channel').show();
        $('#' + nextStep).show();
    }
    return false;
});

$('.manage-explore-select-channel').bind('change', function() {
    // type 1 is for artist based 0 for chapter based
    var getClass = "cell-" + $(this).attr('id');
    var dataString = 'type=0&id=' + $(this).val();
    if ($(this).val() == 0) {
        var no = $(this).attr('id').split('-');
        $('.' + getClass).html('<span class="manage-explore-number">' + no[1] + '</span>');
    } else {
        $.ajax({
            type: 'POST',
            url: "/ajax/get-last-post-image",
            data: dataString
        }).done(function(url) {
            if (url != "") {
                var data = "<img src='" + url + "' class='img-rounded' width='56' height='56' />";
                $('.' + getClass).html(data);
            }
        });
    }
    return false;

});

$('.build-network-chapter-btn').bind('click', function() {
    var nextStep = $(this).parents('section').next('section').attr("id");
    $('section').hide();
    if (add_cover_type == 0) {
        $('#chapter_featured').hide();
        $('#network_featured').show();
    } else if (add_cover_type == 1) {
        $('#network_featured').hide();
        $('#chapter_featured').show();
    }
    $('#' + nextStep).show();
    return false;
});

/*popup for featured chapters in channel cover starts here */
$('.featured-item-containers').delegate('.add-button-icon .enable_chapter_popup', 'click', function() {
    $('#clicked_chapter_container').val($(this).parent().parent().attr('id'));
    $('#manageChapterPopup').modal('show');
});

/*popup for featured chapters in channel cover ends here */

$('#manageChapterPopup').delegate(' .me-chapter-listing .me-chapter-content .me-chapter-post-images-inner img', 'click', function() {
    var id = $(this).attr('data-id');
    $('.me-chapter-post-images-inner').removeClass('video-overlay-active music-overlay-active no-overlay-active');
    var parentClass = "";
    if ($(this).parent('.me-chapter-post-images-inner').hasClass('video-overlay'))
        parentClass = "video-overlay-active";
    else if ($(this).parent('.me-chapter-post-images-inner').hasClass('music-overlay'))
        parentClass = "music-overlay-active";
	else if ($(this).parent('.me-chapter-post-images-inner').hasClass('event-overlay'))
        parentClass = "event-overlay-active";
    else if ($(this).parent('.me-chapter-post-images-inner').hasClass('no-overlay'))
        parentClass = "no-overlay-active";
    $(this).parent('.me-chapter-post-images-inner').addClass(parentClass);
    $('#selectedChapterPost').val(id);
    $('#selectedChapterImageUrl').val($(this).attr('data-url'));
});

$('#btnChapterSelectChapterStory').bind('click', function() {
    var selectedContainer = $('#clicked_chapter_container').val();
    $('#input-' + selectedContainer).val($('#selectedChapterPost').val());
    var width = $('#' + selectedContainer).width() + 'px';
    var height = $('#' + selectedContainer).height() + 'px';
    $('#' + selectedContainer).html('<div class="selectedImageEditsContainer"><img class="selectedImageEdit" data-id = "' + $('#selectedChapterPost').val() + '" src="/images/btn-edit-icons.png" /><img class="selectedImageDelete" data-id = "' + $('#selectedChapterPost').val() + '" src="/images/btn-delete-icons.png" /></div><img src="' + $('#selectedChapterImageUrl').val() + '" class="img-rounded" width="' + width + '" style="float:left;width:100%;" />');
});

$('#channeldropdown').bind('change', function() {
    $('#text-selected-channel').val($('#channeldropdown option:selected').text());
})

$('#featured-overlay-edit').bind('click', function() {
    $('#editFeaturedSelectArtist,#choosechapter').hide();
    $('#editFeaturedList').show();
    $('#editManageNetworkFeaturedPopup').modal('show');
});

/* edit manage network featured items on popup starts here */
$('#editManageNetworkFeaturedPopup').delegate('.modal-body .editManageExploreEnableSlide,.modal-body .editselectedImageEdit', 'click', function() {
    $('#clicked_container').val($(this).parent().parent().attr('id'));
    $('#editFeaturedList').slideUp('slow');
    $('#editFeaturedSelectArtist').slideDown('slow');


});

$('#editManageNetworkFeaturedPopup').find('.modal-body #edit-alpabetic-selector span').on('click', function() {
    var alpha = $(this).text();
    alpha = alpha == "#" ? '' : alpha;
    $.ajax({
        type: 'POST',
        url: '/ajax/get-channel-list-alphabatically',
        data: 'alpha=' + alpha
    }).done(function(resp) {
        $('#edit_artists_selector').html(resp);
    });

});

$('#edit_artists_selector').delegate('.artists', 'click', function() {
    $('.artists').removeClass('active-artist');
    $(this).addClass('active-artist');
    $('#selected_alpha').val($(this).attr('id'));
});

$('#editManageNetworkFeaturedPopup').find('#next-choose-chapter').on('click', function() {
    var id = $('#selected_alpha').val();
    if (id != "")
        $.ajax({
            type: 'POST',
            url: '/ajax/get-chapter-story-list',
            data: 'id=' + id
        }).done(function(resp) {
            $('#story_selector').html(resp);
            $('#editFeaturedSelectArtist').slideUp('slow');
            $('#choosechapter').slideDown('slow');
        });
    return false;
})

$('#btnEditSelectChapterStory').bind('click', function() {
    var selectedContainer = $('#clicked_container').val();
    $('#input-' + selectedContainer).val($('#selectedPost').val());
    var width = $('#' + selectedContainer).width() + 'px';
    var height = $('#' + selectedContainer).height() + 'px';
    $('#editManageNetworkFeaturedPopup #' + selectedContainer).html('<img src="' + $('#selectedImageUrl').val() + '" class="img-rounded" style="width:100%;height:100%;" /><div class="selectedImageEditsContainer"><img class="editselectedImageEdit" data-id = "' + $('#selectedPost').val() + '" src="/images/btn-edit-icons.png" /><img class="editSelectedImageDelete" data-id = "' + $('#selectedPost').val() + '" src="/images/btn-delete-icons.png" /></div>');
    $('#editFeaturedSelectArtist,#choosechapter').hide();
    $('#editFeaturedList').show();

});

$('#editManageNetworkFeaturedPopup').find('#btnPublishFeatured').on('click', function() {
    var featured = [];
    var i = 0;
    $("input[name^='featured_chapter']").each(function() {
        featured[i] = $(this).val();
        i++;
    });
	//commented for disabling the featured posts for MJ
    $('#featuredlist').val(featured);
    $('#editManageNetworkFeaturedPopup').modal('hide');
});

$('.featured-item-containers').delegate('.selectedImageEditsContainer .editSelectedImageDelete', 'click', function() {
    var innrHtml = '<div class="add-button-icon"><img src="/images/add-button-icon.png" class="editManageExploreEnableSlide" alt=""></div>';
    var parentItem = 'input-' + $(this).parent().parent('.featured-item-containers').attr('id');
    $('#' + parentItem).val('0');
    $(this).parent().parent('.featured-item-containers').html(innrHtml);
});

$('#manageNetworkPopup').delegate('.modal-body .channel_chapter_btn_ok img', 'click', function() {
    $('.channel_chapter_btn_ok img').removeClass('active-channel-chapter');
    $(this).addClass('active-channel-chapter');
    $('#selected_post_id').val($(this).attr('data-id'));
    $('#selected_image_url').val($(this).attr('data-url'));
    $('#selected_chapter_name').val($(this).attr('data-name'));

});

$('#confirm-chapter-selection-btn').bind('click', function() {
    var popuptype = $('#popuptype').val();
	var chArray = [];
	$('input[name="artist[]"]').each(function() {
   		 chArray.push( $(this).val() );
	});
	if($.inArray($('#selected_post_id').val(), chArray)>= 0){
		$('#manageNetworkPopup').modal('hide');
		return false;
	}
    if (popuptype == 0) {
        var str = '<div data-id="0" class="col-sm-3 col-md-3 col-xs-3 remove-padding manage-network-small-container">' +
            '<div class="delete-non-featured-edit-section"><img src ="/images/icon-delete-25px.png" class="delete-non-featured-edit-section-img" /></div>' +
            '<img src= "' + $('#selected_image_url').val() + '" width="117" height="117" class="img-rounded" />' +
            '<input type="hidden" name="artist[]" value="' + $('#selected_post_id').val() + '" />' +
            '</div>';
        $('#edit-manage-explore-chapter-add-button').before(str);
    } else {
        var str = '<div class="delete-non-featured-edit-section"><img src ="/images/icon-delete-25px.png" class="delete-non-featured-edit-section-img" /></div>' +
            '<img src= "' + $('#selected_image_url').val() + '" width="117" height="117" class="img-rounded" />' +
            '<input type="hidden" name="artist[]" value="' + $('#selected_post_id').val() + '" />';
        $('#manage-small-' + popuptype).html(str);
    }
    $('#manageNetworkPopup').modal('hide');

});

$('#editManageNetworkFeaturedPopup').delegate('.modal-body .editManageExploreChapterEnableSlide,.modal-body .editselectedChapterImageEdit', 'click', function() {
    $('#clicked_container').val($(this).parent().parent().attr('id'));
    var id = $('#mapping_id').val();
    if (id != "")
        $.ajax({
            type: 'POST',
            url: '/ajax/get-chapter-story-list',
            data: 'id=' + id
        }).done(function(resp) {
            $('#story_selector').html(resp);
            $('#editFeaturedList').slideUp('slow');
            $('#choosechapter').slideDown('slow');
        });
    return false;
});

$('#btnEditChapterSelectChapterStory').bind('click', function() {
    var selectedContainer = $('#clicked_container').val();
    $('#input-' + selectedContainer).val($('#selectedPost').val());
    var width = $('#' + selectedContainer).width() + 'px';
    var height = $('#' + selectedContainer).height() + 'px';
    $('#editManageNetworkFeaturedPopup #' + selectedContainer).html('<img src="' + $('#selectedImageUrl').val() + '" class="img-rounded" style="width:100%;height:100%;" /><div class="selectedImageEditsContainer"><img class="editselectedChapterImageEdit" data-id = "' + $('#selectedPost').val() + '" src="/images/btn-edit-icons.png" /><img class="editSelectedChapterImageDelete" data-id = "' + $('#selectedPost').val() + '" src="/images/btn-delete-icons.png" /></div>');
    $('#choosechapter').hide();
    $('#editFeaturedList').show();

});

$('.featured-item-containers').delegate('.selectedImageEditsContainer .editSelectedChapterImageDelete', 'click', function() {
    var innrHtml = '<div class="add-button-icon"><img src="/images/add-button-icon.png" class="editManageExploreChapterEnableSlide" alt=""></div>';
    var parentItem = 'input-' + $(this).parent().parent('.featured-item-containers').attr('id');
    $('#' + parentItem).val('0');
    $(this).parent().parent('.featured-item-containers').html(innrHtml);
});

$('#chapter-container').delegate('#edit-manage-explore-chapter-add-button', 'click', function() {
    $('#popuptype').val($(this).attr('data-id'));
    $('#manageNetworkPopup').modal('show');
});


$('#user-manage-chapter-container').delegate('.user-manage-chapter-small-container .delete-non-featured-edit-section', 'click', function() {
    var dataid = $(this).parent().attr('data-id');
    if (dataid == 0 || dataid > 8)
        $(this).parent().remove();
    else {
        $(this).parent().html('<span class="manage-explore-number">' + dataid + '</span><input type="hidden" name="artist[]" value="0" /><input type="hidden" name="post[]" value="0" />');
    }
    return false;

});

$('#user-manage-chapter-container').delegate('.user-manage-chapter-small-container span,#user-manage-chapter-add-button', 'click', function() {
    $('#popuptype').val($(this).parent().attr('data-id'));
    $('#manageNetworkPopup').modal('show');
});

$('#confirm-user-chapter-selection-btn').bind('click', function() {
    var popuptype = $('#popuptype').val();
	var chArray = [];
	$('input[name="artist[]"]').each(function() {
   		 chArray.push( $(this).val() );
	});
	if($.inArray($('#selected_post_id').val(), chArray)>= 0){
		$('#manageNetworkPopup').modal('hide');
		return false;
	}
    if (popuptype == 0) {
        var str = '<div data-id="0" class="col-sm-3 col-md-3 col-xs-3 remove-padding user-manage-chapter-small-container ui-state-default can_move">' +
            '<div class="edit-non-featured-edit-section  remove_explore_tab"><img src ="/images/icon-edit-25px.png" data-chaptername = "' + $('#selected_chapter_name').val() + '" class="edit-non-featured-edit-section-img" /></div>' +
            '<div class="delete-non-featured-edit-section  remove_explore_tab"><img src ="/images/icon-delete-25px.png" class="delete-non-featured-edit-section-img" /></div>' +
            '<img src= "' + $('#selected_image_url').val() + '" width="117" height="117" class="img-rounded" />' +
			'<input type="hidden" name="post[]"  value="0" />' +
            '<input type="hidden" name="artist[]" class="hiddenpost" value="' + $('#selected_post_id').val() + '" />' +
            '</div>';
        $('#sortable-explore').append(str);
    } else {
        var str = '<div class="edit-non-featured-edit-section  remove_explore_tab"><img src ="/images/icon-edit-25px.png" data-chaptername = "' + $('#selected_chapter_name').val() + '" class="edit-non-featured-edit-section-img" /></div>' +
            '<div class="delete-non-featured-edit-section  remove_explore_tab"><img src ="/images/icon-delete-25px.png" class="delete-non-featured-edit-section-img" /></div>' +
            '<img src= "' + $('#selected_image_url').val() + '" width="117" height="117" class="img-rounded" />' +
			'<input type="hidden" name="post[]" value="0" />' +
            '<input type="hidden" name="artist[]" class="hiddenpost" value="' + $('#selected_post_id').val() + '" />';
        $('#manage-small-' + popuptype).html(str);
    }
    $('#manageNetworkPopup').modal('hide');

});

$('#confirm-user-chapter-post-selection-btn').bind('click', function() {
    var popuptype = $('#popuptype').val();
    if (popuptype == 0) {
        var str = '<div data-id="0" class="col-sm-3 col-md-3 col-xs-3 remove-padding user-manage-chapter-small-container ui-state-default can_move">' +
            '<div class="edit-non-featured-edit-section  remove_explore_tab"><img src ="/images/icon-edit-25px.png" data-chaptername = "' + $('#selected_chapter_name').val() + '" class="edit-non-featured-edit-section-img" /></div>' +
            '<div class="delete-non-featured-edit-section  remove_explore_tab"><img src ="/images/icon-delete-25px.png" class="delete-non-featured-edit-section-img" /></div>' +
            '<img src= "' + $('#confirmed_image_url').val() + '" width="117" height="117" class="img-rounded" />' +
			'<input type="hidden" name="post[]" value="' + $('#confirmed_post_id').val() + '" />' +
            '<input type="hidden" name="artist[]" class="hiddenpost" value="' + $('#confirmed_post_id').val() + '" />' +
            '</div>';
        $('#sortable-explore').append(str);
    } else {
        var str = '<div class="edit-non-featured-edit-section  remove_explore_tab"><img src ="/images/icon-edit-25px.png" data-chaptername = "' + $('#selected_chapter_name').val() + '" class="edit-non-featured-edit-section-img" /></div>' +
            '<div class="delete-non-featured-edit-section  remove_explore_tab"><img src ="/images/icon-delete-25px.png" class="delete-non-featured-edit-section-img" /></div>' +
            '<img src= "' + $('#confirmed_image_url').val() + '" width="117" height="117" class="img-rounded" />' +
			'<input type="hidden" name="post[]" value="' + $('#confirmed_post_id').val() + '" />' +
            '<input type="hidden" name="artist[]" class="hiddenpost" value="' + $('#confirmed_post_id').val() + '" />';
        $('#manage-small-' + popuptype).html(str);
    }
    $('#manageChannelNonFeaturedPopup').modal('hide');

});

/* edit manage network featured items on popup ends here */


$('.edit_manage_explore_story').bind('click', function() {
    var imgUrl = $('#group_list_span_' + $(this).attr('data-id') + ' .story_cover_image').attr('src');
    $('#edit-manage-explore-featured-image').attr('src', imgUrl);
    $('#storyname').val($('#group_list_span_' + $(this).attr('data-id') + ' .story_name').html());
    $('#selected_post_id').val($(this).attr('data-id'));
    $('#story_cover_image').val($(this).attr('data-url'));
    $('#retinaH').val($(this).attr('data-retinaw'));
    $('#retinaW').val($(this).attr('data-retinah'));
    $('#nonretinaH').val($(this).attr('data-nonretinah'));
    $('#nonretinaW').val($(this).attr('data-nonretinaw'));
    $('#sdH').val($(this).attr('data-sdh'));
    $('#sdW').val($(this).attr('data-sdw'));
    $('#storyEditModal').modal('show');

});

$('#confirm-story-image-change-btn').bind('click', function() {															
    var id = $('#selected_post_id').val();
	var imgUrl = $('#story_cover_image').val();
    if (id != "")
        $.ajax({
            type: 'POST',
            url: '/ajax/update-network-story-cover',
            data: 'id=' + id + '&imgUrl=' + imgUrl + '&retinaH=' + $('#retinaH').val() + '&retinaW=' + $('#retinaW').val() + '&nonretinaH=' + $('#nonretinaH').val() + '&nonretinaW=' + $('#nonretinaW').val() + '&sdH=' + $('#sdH').val() + '&sdW=' + $('#sdW').val()
        }).done(function(resp) {
			$('#group_list_span_' + id + ' .story_cover_image').attr('src', $('#edit-manage-explore-featured-image').attr('src'));
            $('#storyEditModal').modal('hide');
        });
    return false;
});


$('#btnEditExploreSave').bind('click', function() {
	var id = $('#selected_id').val(); 
	var imgUrl = $('#story_cover_image').val();
    if (id != "")
        $.ajax({
            type: 'POST',
            url: '/ajax/update-network-story-cover',
            data: 'id=' + id + '&imgUrl=' + imgUrl + '&retinaH=' + $('#retinaH').val() + '&retinaW=' + $('#retinaW').val() + '&nonretinaH=' + $('#nonretinaH').val() + '&nonretinaW=' + $('#nonretinaW').val() + '&sdH=' + $('#sdH').val() + '&sdW=' + $('#sdW').val() + '&type='+ $('#type').val()
        }).done(function(resp) {
			 location.href = "/cms/superadmin/list-channelgroups";
        });
    return false;
});


$('#btnEditUserManageChapters').bind('click', function() {
	//disabled fro release pourpose
    $('#user_manage_chapters').submit();
});

$(function() {
   /* $("#sortable-explore").sortable({
        items: "div:not(.remove_explore_tab)"
    });
    $("#sortable-explore").disableSelection();*/

    $(".slide-show-container").sortable({
        update: function( event, ui ) {
            $.ajax({
                type: 'POST',
                url: '/ajax/sort-slides',
                data:  $('#slideshow_view_form').serialize()
            }).done(function(res) {
            });
        }
        //items: "div:not(.remove_explore_tab)"
    });
    $(".slide-show-container").disableSelection();
});

$('#featured-overlay-user-manage-channel').bind('click', function() {
    $('#editFeaturedSelectArtist,#choosechapter').hide();
    $('#editFeaturedList').show();
    $('#editManageNetworkFeaturedPopup').modal('show');
});
/*
$('#sortable-explore').delegate('.edit-non-featured-edit-section-img', 'click', function() {
	$('#popuptype').val($(this).parent().parent().attr('data-id'));																					 
	$('#manageChannelNonFeaturedPopup').modal('show');	
	
 
});*/

$('#confirm-chapter-story-image-change-btn').bind('click', function() {
    var formData = $('#form_user_edit_chapters').serialize();
    if ($('#chapterName').val() != "")
        $.ajax({
            type: 'POST',
            url: '/ajax/update-artist-chapter-story-cover',
            data: formData
        }).done(function(resp) {
            $('#storyEditModal').modal('hide');
            location.reload();
        });
    return false;
});

$('#storyEditModal').on('hidden.bs.modal', function() {
    $('#storyEditModal input[type=hidden]').val('');
});

$('#publish_channel_now').bind('click', function() {
	$('#confirmChannelPublishModal').modal('show');												 	
});

$('#confirmChannelPublishButton').bind('click', function() {
	var id= $('#publish_channel_now').data('mappingid');
	if (id != ""){
        $.ajax({
            type: 'POST',
            url: '/ajax/update-manage-explore-flag',
            data: 'id='+id
        }).done(function(res) {
            if(res == 1){
				$('#publish-channel-bar').slideUp();
			}
			$('#confirmChannelPublishModal').modal('hide');	
        });
    }
    return false;
});

function initiateVideoPoster(){
	if($('#featured_post').val()!=""){
		$('.shortlink-video-container video').on('timeupdate', function() {																
			if (this.currentTime > 5){
				this.currentTime = 5;
				$('.shortlink-video-container video').trigger('pause');
				if (document.exitFullscreen)
					document.exitFullscreen();
				else if (document.mozCancelFullScreen)
					document.mozCancelFullScreen();
				else if (document.webkitCancelFullScreen)
					document.webkitCancelFullScreen();
				$('.shortlink-video-container video')[0].webkitExitFullScreen();
				$('.shortlink-video-container video').hide();
				$("#put-lock #put-lock-image").html('<img src="/images/video-lock.png" class="" alt="">');
				$('#put-lock').show();
			}
		});
	}
}

function initiateAudioPoster(){
	if($('#featured_post').val()!=""){	
		$('.shortlink-audio-container audio').on('timeupdate', function() {														
			if (this.currentTime > 10){
				//$('.shortlink-audio-container audio').trigger('pause');
				this.currentTime  = 10;
				$('.shortlink-audio-container audio').remove();
				$("#put-lock #put-lock-image").html('<img src="/images/audio-lock.png" class="" alt="">');
				$('#put-lock').show();
			}
		});
	}
}



$('.primary_video_toggle').bind('click', function() {
    Scripts.calculateVideoContainerDimension();
    $(this).hide();
	primaryVideoToggle($(this).attr('data-url'));

});

function primaryVideoToggle(videoUrl){
	$('.secondary-content').css('display', 'none');
    $('.feed-popup-player').css('display', 'block');
    if ($('#secpopupVideoId').length > 0)
        $('#secpopupVideoId').trigger("pause");
    if ($('#secpopupAudioId').length > 0)
        $('#secpopupAudioId').trigger('pause');
    if ($('#popupVideoId').length > 0) {
        $('.popupVideo').css('display', 'block');

    }
	var fname = videoUrl.substring(videoUrl.lastIndexOf('/') + 1, videoUrl.lastIndexOf('.')); //get file name without extension
    $('#primary-content').html('');
	$('#primary-content').html('<div class="popupVideo"><video id="popupVideoId" width="320" height="240" preload="auto" controls>\n\
    		<source src="' + urlScripts.getS3Url(urlScripts.getVideoUrl(fname + ".webm")) + '" type="video/webm">\n\
			<source src="' + urlScripts.getS3Url(urlScripts.getVideoUrl(fname + ".mp4")) + '" type="video/mp4">\n\
        	Your browser does not support the video tag.\n\
        </video></div>');
	if($('#primary-content').hasClass('shortlink-video-container'))
		initiateVideoPoster();
	if($('#primary-content').hasClass('shortlink-audio-container'))
		initiateAudioPoster();
    var iOS = !!navigator.platform && /iPad|iPhone|iPod/.test(navigator.platform);
    if(iOS === false){
	   Scripts.makeFullScreen('popupVideoId');
    }else{
        $('#popupVideoId').trigger('play');
    }
	if($('.primary_video_player_toggle').hasClass('shortlink-video-container'))
		initiateVideoPoster();
	var vid = document.getElementById("popupVideoId");
	vid.addEventListener('webkitendfullscreen', function (e) {
		$('#popupVideoId').trigger('pause');
        $('.popupVideo').css('display', 'none');
        $('.popup-video-icon').css('display', 'block');													  
	});
    $('.popupVideo').delegate('#popupVideoId', 'webkitfullscreenchange mozfullscreenchange fullscreenchange', function(e) {
        var state = document.fullScreen || document.mozFullScreen || document.webkitIsFullScreen;
        var event = state ? 'FullscreenOn' : 'FullscreenOff';
        if (event == "FullscreenOff") {
            $('#popupVideoId').trigger('pause');
            $('.popupVideo').css('display', 'none');
            $('.popup-video-icon').css('display', 'block');
        }
    });
    document.addEventListener("mozfullscreenchange", function(event) {
        var state = document.fullScreen || document.mozFullScreen || document.webkitIsFullScreen;
        var event = state ? 'FullscreenOn' : 'FullscreenOff';
        if (event == "FullscreenOff") {
            $('#popupVideoId').trigger('pause');
            $('.popupVideo').css('display', 'none');
            $('.popup-video-icon').css('display', 'block');
        }
    });
	
}

$('body').delegate('#remove-channel-image','click',function(){
		$('#network_cover').val("");
		$('#manageexplore-edit-network-cover').attr('src',$('#network_cover_profile').val());
		$(this).remove();
});

/*$( "#seemorevast_img" ).hover(function() {
	$(this).css('background-image', 'url(/images/vast_ticket_btn_web-active-bg.png)');
},function(){
	$(this).css('background-image', 'url(/images/vast_ticket_btn_web-normal-bg.png)');
});*/


$(document).ready(function(){
						   
	// for youtube flow starts
	$( ".video-progress-bar" ).each(function( index ) {
		var progressID = '.video-progress-bar'+index;
		var post_id = $(progressID+' .postIDHidden').val();
		eval("myVar" + index + "= setInterval(function(){ myTimer(index,post_id); }, 20000)");

	});	
	// for youtube flow ends
	
});
// timer for youtube video conversion starts 
function myTimer(index,post_id){
    console.log("percentage");
	var progressID = '.video-progress-bar'+index;
	var ctime = $(progressID+' .progressHidden').val();
	$.ajax({
		type: 'POST',
		url: '/ajax/update-video-conversion-percent',
		data: {
			post_id: post_id
		}
	}).done(function(percent) {

        console.log("res "+percent);
		if(percent == -1){
			var myInc = $(progressID+' .progressHidden').val();
			setInterval(function(){ 
				$(progressID+' .video-progress-bar-progress').css('width',myInc+'%');
				$(progressID+' .video-progress-bar-pending').css('width',parseInt(100-myInc)+'%').css('border-top-left-radius','0px').css('border-bottom-left-radius','0px');
				$(progressID+' .video-progress-bar-percent-text').text('('+myInc+'%)');
				myInc++;
				if(myInc==100){
					clearInterval(eval("myVar" + index));
					$(progressID).remove();
					location.href = "/cms/feeds";
				}
			}, 100);
		}else{
			if(percent > 0){
				$(progressID+' .video-progress-bar-progress').css('width',percent+'%');
				$(progressID+' .video-progress-bar-pending').css('width',parseInt(100-percent)+'%').css('border-top-left-radius','0px').css('border-bottom-left-radius','0px');
				$(progressID+' .video-progress-bar-percent-text').text('('+percent+'%)');
				$(progressID+' .progressHidden').val(percent);
			}
		}
	});
}

// timer for youtube video conversion starts 


// for twitter video & audio sharing starts here 
if($('#twitterPlayerContainer').hasClass('isFeaturedVideo'))
	initiateVideoPosterForTwitterVideo();
	
function initiateVideoPosterForTwitterVideo(){
	$('#twitterPlayerContainer video').on('timeupdate', function() {																
		if (this.currentTime > 5){
			this.currentTime = 5;
			$('#twitterPlayerContainer video').trigger('pause');
			$('#put-lock').show();
		}
	});
	
}

if($('#twitterPlayerContainer').hasClass('isFeaturedAudio'))
	initiateAudioPosterForTwitterAudio();

function initiateAudioPosterForTwitterAudio(){
	$('#twitterPlayerContainer audio').on('timeupdate', function() {														
		if (this.currentTime > 10){
			$('#twitterPlayerContainer audio').trigger('pause');
			this.currentTime  = 10;
			$('#put-lock').show();
		}
	});
}


// for twitter video & audio sharing ends here 

/* VIP Subscription and schedule script starts */
$('.vip-share-publish-section .vip-radio').bind('click', function() {
    if ($(this).is(':checked')) {
        $('.vip-share-publish-section .publish-radio-msgRadioText').css('color', '#fff');
        $(this).parent().find('.msgRadioText').css('color', '#fff');
        if ($(this).val() == 1) {
            $('#vip_schedule_content_div').slideDown('slow');
        } else {
            $('#vip_schedule_content_div').slideUp('slow');
        }
    }
});
$('#vipuser1').click(function() {
    if ($(this).is(':checked')) {
        $('.msgRadioText').css('color', '#868686');
        $(this).parent().find('.msgRadioText').css('color', '#da3838');
    }
});
 $('#vipuser2').click(function() {
    if ($(this).is(':checked')) {
        $('.msgRadioText').css('color', '#868686');
        $(this).parent().find('.msgRadioText').css('color', '#da3838');
    }
});
/* VIP subscription and schedule script ends */

/* delete posts */
$('#confirmPostdelete').find('.modal-footer #confirm').on('click', function() {
        $(this).addClass('wait_symbol');
        var postId  = $("input[name*='post_id']").val();
        var formUrl = '/post/'+postId+'/delete_post/';
        $.ajax({
            type: 'POST',
            url: formUrl,
            data: postId
        }).done(function(msg) {
            $('#confirmPostdelete').modal('hide');
            $('.content-area').hide();

            if (msg == 1) {
                location.href = "/cms/feeds";
            }
            //getPostFeeds(true);
        });
        return false;        
});

// share post 
$("#sharePostForm").submit(function(e) {
        if ($.active > 0)
            return false;        
        e.preventDefault();
        var formData = new FormData($(this)[0]);
        formData.append('fb_page_data', $('#fb_page_data').val());
        $.ajax({
            type: 'POST',
            url: '/ajax/share-post',
            data: formData,
            processData: false,
            contentType: false,
        }).done(function(msg) {
                $(".account-save").removeAttr("disabled"); 
                
                if (msg.success == false) {
                    var arr = msg.errors;
                    var errMsg = "";
                    $.each(arr, function(index, value) {
                        if (value.length != 0) {
                            errMsg += value + "<br/>";
                        }
                    });
                    $('#myModalMessage').modal('show');
                }

                if (msg == 1) {
                    // location.href = "/cms/feeds";
                    $('#myModalMessage').modal('show');
                }
        });

        return false;
});

/* livestream golive push notification starts here */
$('#livestreamGoLive').bind('click', function(e) {
    e.preventDefault();
    var paid = $('input[name=msg-users]:checked', '#add-post-page form').val();
    var formData = 'subscription=' + paid;
    $.ajax({
        type: 'POST',
        url: '/ajax/livestream-go-live',
        data: formData
    }).done(function(resp) {
        if (resp == 1)
            location.href = "/cms/feeds";
    });

});
/* livestream golive push notification ends here */

/* slide show image edit starts here */
$('#btn_slideshow_edit_save').bind('click', function() {                                                         
    var formData = 'id=' + $('#slide').val() + '&title=' + $('#title').val() +'&description='+ $('#description').val() +'&filename=' + $('#filename').val() +'&sdW='+ $('#sdW').val() +'&sdH='+ $('#sdH').val() +'&hdW='+ $('#hdW').val() +'&hdH='+ $('#hdH').val() +'&thumbW='+ $('#thumbW').val() +'&thumbH='+ $('#thumbH').val()+'&type=edit';
    if ($('#slide').val() != "")
        $.ajax({
            type: 'POST',
            url: '/ajax/update-slide-details',
            data: formData,
        }).done(function(resp) {
            if (resp == 1) {
                location.href = "/cms/superadmin/slide-show";
            }
        });
    return false;
});
/* slide show image edit ends here */

/* slide show image delete ends here */
$('.delete_slide_btn').bind('click',function(){
    var type = $(this).hasClass('view_delete') ? 0 : 1;
    var index = $(this).attr('data-index');
    var formData = 'id=' + $(this).attr('data-id') + '&title=&description=&filename=&sdW=0&sdH=0&hdW=0&hdH=0&thumbW=0&thumbH=0&type=delete';
    if ($('#slide').val() != ""){
         $('#confirmation_delete').modal({ backdrop: 'static', keyboard: false })
        .one('click', '#confirm_slide_delete', function() {
           $.ajax({
            type: 'POST',
            url: '/ajax/update-slide-details',
            data: formData,
            }).done(function(resp) {
                if (resp == 1) {
                    if(type == 0){
                        $('#slide-show-slide-'+index).removeClass('has_slide').addClass('blank_slide');
                        $('#slide-container-'+index).removeClass('has_slide').addClass('blank_slide');
                        $('#confirmation_delete').modal('hide');
                    }else{
                        location.href = "/cms/superadmin/slide-show";
                    }
                }
            });
        });
    }
    return false;
})




/* slide show image delete ends here */
