function base64ArrayBuffer(arrayBuffer) {
    var base64    = '';
    var encodings = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';

    var bytes         = new Uint8Array(arrayBuffer);
    var byteLength    = bytes.byteLength;
    var byteRemainder = byteLength % 3;
    var mainLength    = byteLength - byteRemainder;

    var a, b, c, d;
    var chunk;

    // Main loop deals with bytes in chunks of 3
    for (var i = 0; i < mainLength; i = i + 3) {
        // Combine the three bytes into a single integer
        chunk = (bytes[i] << 16) | (bytes[i + 1] << 8) | bytes[i + 2];

        // Use bitmasks to extract 6-bit segments from the triplet
        a = (chunk & 16515072) >> 18; // 16515072 = (2^6 - 1) << 18
        b = (chunk & 258048)   >> 12; // 258048   = (2^6 - 1) << 12
        c = (chunk & 4032)     >>  6; // 4032     = (2^6 - 1) << 6
        d = chunk & 63;               // 63       = 2^6 - 1

        // Convert the raw binary segments to the appropriate ASCII encoding
        base64 += encodings[a] + encodings[b] + encodings[c] + encodings[d];
    }

    // Deal with the remaining bytes and padding
    if (byteRemainder == 1) {
        chunk = bytes[mainLength];

        a = (chunk & 252) >> 2; // 252 = (2^6 - 1) << 2

        // Set the 4 least significant bits to zero
        b = (chunk & 3)   << 4; // 3   = 2^2 - 1

        base64 += encodings[a] + encodings[b] + '==';
    } else if (byteRemainder == 2) {
        chunk = (bytes[mainLength] << 8) | bytes[mainLength + 1];

        a = (chunk & 64512) >> 10; // 64512 = (2^6 - 1) << 10
        b = (chunk & 1008)  >>  4; // 1008  = (2^6 - 1) << 4

        // Set the 2 least significant bits to zero
        c = (chunk & 15)    <<  2; // 15    = 2^4 - 1

        base64 += encodings[a] + encodings[b] + encodings[c] + '=';
    }

    return base64;
}

function processMovablePart1() {
    var fileInput = document.getElementById("p1file");
    var fileList = fileInput.files;
    if (fileList.length === 1 && fileList[0].size === 0x1000) {
        var file = fileInput.files[0];
        var fileReader = new FileReader();
        fileReader.readAsArrayBuffer(file);
        fileReader.addEventListener("loadend", function (){
            var arrayBuffer = fileReader.result;
            var lfcsBuffer = arrayBuffer.slice(0, 8);
            var lfcsArray = new Uint8Array(lfcsBuffer);
            var textDecoder = new TextDecoder();
            var lfcsString = textDecoder.decode(lfcsArray);
            if (lfcsString === textDecoder.decode(new Uint8Array(8))) {
                alert("movable_part1.sed is invalid");
                return
            }
            document.getElementById("part1b64").value = base64ArrayBuffer(lfcsBuffer);
            var id0Buffer = arrayBuffer.slice(0x10, 0x10+32);
            var id0Array = new Uint8Array(id0Buffer);
            var id0String = textDecoder.decode(id0Array);
            if (btoa(id0String) !== "AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=") { // non blank, if id0 is injected with seedminer_helper
                var id0Input = document.getElementById("id0");
                id0Input.disabled = true;
                id0Input.value = id0String;
            }
        })
    }
}

jQuery(function () {
    function changeStep(wantedStep) {
        jQuery('#accordion button[data-toggle="collapse"]:not([data-target="#collapseOne"])').prop('disabled', true);
        jQuery('#accordion .collapse.show:not(#collapseOne)').removeClass('show');
        if (wantedStep === 1) {
            localStorage.removeItem('taskId');
            jQuery('button[data-target="#collapseTwo"]').prop('disabled', false);
            document.getElementById("beginButton").disabled = false;
            jQuery('#collapseTwo').addClass('show');
        } else if (wantedStep === 2) {
            jQuery('#id0Fill').html(localStorage.getItem('id0'));
            jQuery('button[data-target="#collapseFour"]').prop('disabled', false);
            jQuery('#collapseFour').addClass('show');

            console.log('request status from server every 5 seconds');

            var currentTask = localStorage.getItem('taskId');
            function checkStatusForCurrentTask() {
                jQuery.ajax({
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'get-state',
                        taskId: localStorage.getItem('taskId')
                    },
                    success: function (data) {
                        if (currentTask === localStorage.getItem('taskId')) {
                            var currentState = data.currentState;

                            if (currentState === -1) {
                                alert("Your request was aborted. Your part1 and id0 was most likely unable to be mined successfully. Check your inputs and ask for help on the Homebrew Discord.");
                                changeStep(1);
                            }
                            if (currentState === -2) {
                                alert("Your request took too long to bruteforce. Your part1 and id0 was most likely unable to be mined successfully.");
                                changeStep(1);
                            }

                            jQuery("#bfProgress").removeClass("bg-warning");

                            if (currentState === 1) {
                                jQuery("#bfProgress").addClass("bg-warning");
                                jQuery("#bfProgress").text("Bruteforcing...");
                            }
                            if (currentState === 2) {
                                changeStep(3);
                            }

                            if (currentState === 0 || currentState === 1) {
                                setTimeout(checkStatusForCurrentTask, 2000);
                            }
                        }
                    }
                });

            }
            checkStatusForCurrentTask();
        } else if (wantedStep === 3) {

            jQuery('button[data-target="#collapseFive"]').prop('disabled', false);
            jQuery('#collapseFive').addClass('show');

            jQuery('#downloadMovable').attr('href', '/get_movable?task=' + localStorage.getItem('taskId') );
            jQuery('#downloadMovable2').attr('href', '/get_movable?task=' + localStorage.getItem('taskId') );

        }
    }

    if (localStorage.getItem('taskId') && localStorage.getItem('finished') === null) {
        changeStep(2);
    } else {
        changeStep(1);
    }

    jQuery(document).on('click', '#cancelButton3, #cancelButton, #cancelButton1', function () {
        jQuery.ajax({
            type: 'POST',
            dataType: 'json',
            data: {
                action: 'cancel',
                taskId: localStorage.getItem('taskId')
            },
            success: function () {
            }
        });
       changeStep(1);
    });
    document.getElementById("p1file").addEventListener('change', function () {
        processMovablePart1();
    });


    var beginButton = document.getElementById("beginButton");
    beginButton.addEventListener("click", function (e) {
        e.preventDefault();
        document.getElementById("beginButton").disabled = true;
        document.getElementById("id0").value = document.getElementById("id0").value.toLowerCase();
		var gRecaptchaResponse = jQuery('#g-recaptcha-response').val()
		
		if (gRecaptchaResponse.length < 1) {
			alert("Please verify that you are human before submitting this form.");
			return;
		}
		
        if (document.getElementById("part1b64").value.length === 0) {
            processMovablePart1();
        }

        if (document.getElementById("part1b64").value.length >= 0) {

            localStorage.setItem("id0", document.getElementById("id0").value);
            localStorage.setItem("part1b64", document.getElementById("part1b64").value);

            jQuery.ajax({
                type: 'POST',
                dataType: 'json',
                data: {
                    id0: document.getElementById("id0").value,
                    part1b64: document.getElementById("part1b64").value,
					gRecaptchaResponse: gRecaptchaResponse
                },
                success: function (data) {
                    if (data.success === true) {
                        localStorage.setItem("taskId", data.taskId);
                        changeStep(2);
                    } else {
                        alert(data.message);
                    }
                }
            });

        }
    });


});
