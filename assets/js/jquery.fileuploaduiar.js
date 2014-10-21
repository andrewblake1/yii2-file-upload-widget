/**
 * jquery.fileuploaduiar.js
 * 
 * Configures blueimp's jquery-file-upload for a single request saves. I.e.
 * caches a list of files to upload, and to delete and sends on submit as a
 * single request, packaged with any form data. If any part of form validation
 * fails the the response should indicate form and file errors and will
 * reinstate the list of files to upload and potentially undelete deleted files
 * from the deleted list if validation failed due to being required.
 * 
 * Allows for multiple upload widgets, with mulitple files, and validation per file
 * and per widget e.g. maxFiles
 * 
 * TODO: currently using yiiActiveForm to update failed form attributes fails
 * (apart from the hack here) due to local scoping of the required function
 * updateInputs which for this to work needs to be added to the exposed methods
 * object in yiiActiveForm. Could possibly just make an altered copy of
 * yiiActiveForm possibly however have raised issue on github and support forum
 * but will be extreme low priority to look into I imagine so might have to
 * copy yiiActiveForm. current simple hack to yiiActiveForm is adding the
 * following 3 lines to methods:
 * 
 *         updateInputs: function (messages) {
 *             updateInputs($(this), messages, true);
 *         },
 *
 * @author Andrew Blake <admin@newzealandfishing.com>
 * @package dosamigos\fileupload
 * @license don't know the license agreement for -- help yourself in anyway you like
 */
 
function fileuploaduiar (options, fileUploadTarget, paramName, urlGetExistingFiles) {

    $(fileUploadTarget).fileupload(options);

    // custom getFilesFromResponse due to possible multiple widgets
    $(fileUploadTarget).fileupload(
        'option',
        'getFilesFromResponse',
        function (data) {
            if (data.result && $.isArray(data.result[paramName])) {
                return data.result[paramName];
            }
            return [];
        }
    );

    // Load existing files
    $(fileUploadTarget).each(function() {
        $(this).addClass('fileupload-processing');
        $.ajax({
            // Uncomment the following to send cross-domain cookies:
            //xhrFields: {withCredentials: true},
            url: urlGetExistingFiles,
            dataType: 'json',
            context: $(this)[0]
        }).always(function () {
            $(this).removeClass('fileupload-processing');
        }).done(function (result) {
            $(this).fileupload('option', 'done')
                .call(this, $.Event('done'), {result: result});
        });
    });
}
 
jQuery(document).ready(function () {
    // keeping track of the files ourselves
    var filesList = [], paramNames = [];

    // attach jquery-file-upload to the start button bar div - needs to surround
    // the whole widget to make use of progress bar
    var primaryTarget = $('.fileupload-buttonbar');
    primaryTarget.fileupload({
        url : $('form').attr('action')
    });

    // alter the standard delete action to hide the row and and an input to post the deletes on save
    $('div[id$="-files-container"]').on("fileuploaddestroy", function(e, data){
        e.preventDefault();
        var container = $(e.target).closest('div[id$="-files-container"]');
        var name = $('input[type="file"]', container).attr('name');
        // create a hidden input to post this file to delete
        var button = $(e.toElement);
        $('<input>').attr({
            type: 'hidden',
            value: data.url,
            name: 'delete[' + name.replace(/[\[\]']+/g,'') + '][]'
        }).insertAfter(button);
        // hide this row
        button.closest('tr').toggleClass('in').hide('slow');
        // clear out any active form error message as may no longer be relevant
        container.next('div.help-block').html('');
    });

    // on add file, save reference to the file in a global array so that we can access later to send
    $('div[id$="-files-container"]').on("fileuploadadd", function(e, data){
        filesList.push(data.files[0]);
        paramNames.push(e.delegatedEvent.currentTarget.name);
        // clear out any active form error message as may no longer be relevant
        $(this).next('div.help-block').html('');
    });

    // block submit thru file upload - will happen by direct call above - even though would have though taking over click would have
    // done this it does so guiessing send calls submit first or something and perhaps empties the q
    $('div[id$="-files-container"]').bind('fileuploadsubmit', function (e, data) {
        e.preventDefault();
    });

    // deal with click events on the save button
    $('button[type="button"].start').on('click',function () {
        // append the form data
        primaryTarget.fileupload({
            formData: $('form').serializeArray()
        });
        // if there are some files in our upload q
        if(filesList.length) {
            // send them programatically
            primaryTarget.fileupload('send', {files:filesList, paramName: paramNames});
        } else {
            // fake it so that fileupload send will run
            primaryTarget.fileupload('send', {files:'dummy to make the send fire'});
        }
    });

    // because cancelled are added by client we don't easily have access to the click function here so use event bubbling to pick it up
    // at the form then check if the original element clicked was a cancel button  - allow normal processing afterwards
    $('form').click(function(e) {
        if($(e.target).hasClass('cancel')) {
            var target = $(e.target);
            var container = target.closest('div[id$="-files-container"]');
            // need to figure out which file to remove from our globalFiles list added to in add
            // get paramName - which is the name nearest file input field above this element in the dom
            var name = $('input[type="file"]', container).attr('name');
            // get the row number this element resides in within this table - only considering cancelable rows
            var rowIndex = $('button.cancel', target.closest('tbody')).closest('tr').index(target.closest('tr'));

            // remove this from fileList and paramNames, paired arrays but not grouped
            var atRow = 0;
            $(paramNames).each(function (i, paramName) {
                if(name == paramName) {
                    if(atRow == rowIndex) {
                        paramNames.splice(i, 1);
                        filesList.splice(i, 1);
                    } else {
                        atRow++;
                    }
                }
            });

            // clear out any active form error message as may no longer be relevant
            container.next('div.help-block').html('');
        }
    });

    // set call back for when upload process done - to block removal of the file input in case of error
    // basically we do want to show file upload errors but return others to there pre-upload state
    primaryTarget.bind('fileuploaddone', function (e, data) {
        var paramName;
        e.preventDefault();
        // if there are errors there will be no redirect member in our json response from the server
        if(data.result.hasOwnProperty('redirect')) {
            // redirect - no errors in form data
            window.location.href = data.result.redirect;
        }
        else {
            // loop thru each member of the response
            $.each(data.result, function(paramName, value){
                // skip form errors key
                if(paramName == 'activeformerrors') {
                    return true;
                }
                // loop thru each of the rows in our fileupload widget
                $('tr.template-upload.fade.in').each(function (index) {
                    var file = data.result[paramName][index];
                    var error;
                    // if an error was returned from the server
                    if(file && file.hasOwnProperty('error')) {
                        // set an error for display
                        error = file.error;
                    }
                    // am assuming any empty result can only come from create within updateAction where ActiveRecord:save() failed
                    // but will get into this else as well if no error - discarding empty result error in jqeury.fileupload-ui.js done
                    // handler
                    else {
                        // enable the start button - even though it is hidden
                        $('button.btn.btn-primary.start', this).prop('disabled', false);
                    }

                    if(error) {
                        // display error
                        $('.error.text-danger', this).html(error);
                    }
                });
            });

            // if need to restore in deleted files due to unique validator failure
            if(data.result.hasOwnProperty('restore')) {
                $.each(data.result.restore, function (paramName, fileNames) {
                    var container = $('#' + paramName + '-files-container');
                    $.each(fileNames, function () {
                        var button = $('[data-url="' + this + '"]', container);
                        button.closest('tr').addClass('in').show('slow');
                        // remove the deleted input
                        button.find('input[name^="delete"]').remove();
                    });
                });
            }

            // if form errors
            if(data.result.hasOwnProperty('activeformerrors') && !data.result.activeformerrors.hasOwnProperty('length')) {
                // use yii to deal with the error
                $('form').yiiActiveForm('updateInputs', data.result.activeformerrors);
            }

            // if non attribute form errors - e.g. trigger reported errors, fk constraint errors etc
            $('#nonattributeerrors').html(data.result.hasOwnProperty('nonattributeerrors') ? data.result.nonattributeerrors : '');
        }
    });
});
