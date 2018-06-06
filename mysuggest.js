var dialog =null;
var dM = null;
jQuery(document).ready(function(){

    dialog = jQuery( "#modal_suggestion_dialog" ).dialog({
        autoOpen: false,
        height: 400,
        width: 350,
        modal: true,
        buttons: {
                Cancel: function() {
                        dialog.dialog( "close" );
                }
        },
        close: function() {
                jQuery('#emailSug').val('');
                jQuery('#messageSug').val('');
        }
    });

    dM = jQuery( "#dialog-message" ).dialog({
      autoOpen: false,
      modal: true,
      buttons: {
        Ok: function() {
          jQuery( this ).dialog( "close" );
        }
      }
    });

    dialog.dialog( "open" ); //we open the modal
});

function blockSuggest(block){
    if(block){
        jQuery('button.ui-button').prop( "disabled", true );
        jQuery('#suggestLoading').css('display', 'inline');
    }
    else{
        jQuery('#suggestLoading').css('display', 'none');
        jQuery('button.ui-button').prop( "disabled", false );
    }
}

function ajaxTheSuggestion(){
    blockSuggest(true); //We prevent user from clicking multiple time the submit button

    var pURL = jQuery('#modal_suggestion_dialog form').attr('action');
    var pEmail = jQuery('#emailSug').val();
    var pMsg = jQuery('#messageSug').val();
    var pAction = jQuery('#actionSug').val();
    var pCSRF = jQuery('#_wpnonce').val();
    var pRef = jQuery("input[name='_wp_http_referer']" ).val();

    
    //do ajax post here
    jQuery.ajax({
        method: "POST",
        data: { email: pEmail, message: pMsg, action: pAction, _wpnonce: pCSRF, _wp_http_referer: pRef},
        url: pURL
    }).done(function(msg){
        if(msg.success){
            dialog.dialog( "close" );
            jQuery('#ajaxRetMsg').html('Thank you. You suggestion:<br/> '+msg.suggestion+'<br/><br/>has been saved.'); //msg.suggestion is sanitized before showing it in the HTML!
            dM.dialog("open");
        }
        else{
            jQuery('#ajaxRetMsg').html('<span style="color: red;">Error: '+msg.error+'</span>');
            dM.dialog("open");
        }
        blockSuggest(false);
    }).fail(function(msg){
        blockSuggest(false);
    });
}

