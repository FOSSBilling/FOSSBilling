/**
 * FOSSBilling
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

$(function() {
	$('.wizard').smartWizard({
		selected: 0,  // Selected Step, 0 = first step
		keyNavigation: false, // Enable/Disable key navigation (left and right keys are used if enabled)
		enableAllSteps: false,  // Enable/Disable all steps on first load
		transitionEffect: 'fade', // Effect on navigation, none/fade/slide/slideleft
		contentURL:null, // specifying content url enables ajax content loading
		contentCache:false, // cache step contents, if false content is fetched always from ajax url
		cycleSteps: false, // cycle step navigation
		enableFinishButton: false, // makes finish button enabled always
		errorSteps:[],    // array of step numbers to highlighting as error steps
		labelNext:'Next', // label for Next button
		labelPrevious:'Previous', // label for Previous button
		labelFinish:'Finish',  // label for Finish button
        // Events
		onLeaveStep: function(o, c) { if (c.fromStep < c.toStep) { return validateSteps(c.fromStep); } else { return true; } }, // triggers when leaving a step
        onShowStep: function(o, c) { return showStep(c.toStep); },  // triggers when showing a step
		onFinish: doFinish  // triggers when Finish button is clicked
	 });

    $("#overlay").ajaxStart(function() {
        var o = $('.wizard').offset();
        $(this).css('height', $('.wizard').height()-2).offset({top: o.top+1, left:o.left+1}).show();
    }).ajaxStop(function() {
        $(this).hide();
    });
});

    function doFinish() {
        document.getElementById('installer').style.display = "none"; // Hide the form
        document.getElementsByClassName('leftNav')[0].style.width = "980px" // Make the sidebar wider
    }

    function showStep(stepnumber) {
        [...document.getElementsByClassName('step')].forEach(function(step) {
            step.style.display = 'none'; // Hide every step
        });

        [...document.getElementsByClassName('step-'+stepnumber)].forEach(function(step) {
            step.style.display = ''; // Make the current step and its sidebar visible
            step.classList.replace('hide', 'show'); // Animate the sidebar
        });

    }

    function validateSteps(stepnumber) {
        var url = installURL+'install.php?a=';
        var el = document.getElementById('installer');
        var data = new URLSearchParams(new FormData(el)); // Docs: https://developer.mozilla.org/en-US/docs/Web/API/URLSearchParams
        var ok = false;

        if (stepnumber == 1) {
            var checkbox = document.getElementById("agree");
            if (!checkbox.checked) {
                alert('You must agree to the terms of service');
                return false;
            }
        }

        if (stepnumber == 2) {
            if (isEmpty('db_host')) return false;
            if (isEmpty('db_name')) return false;
            if (isEmpty('db_user')) return false;

            fetch(url+'check-db', {
                method: 'POST',
                body: data
              }).then(function(response) {
                return response.text();
              }).then((data) => {
                if (data != 'ok') {
                    alert(data)
                } else {
                    ok = true;
                }

                return ok;
            });
        }

        if (stepnumber == 3) {
            if (isEmpty('admin_name')) return false;
            if (isEmpty('admin_email')) return false;
            if (isEmpty('admin_pass')) return false;
            if (confirm('FOSSBilling installer will create database. It may take some time. Do not close this window. Continue?')) {

                fetch(url+'install', {
                    method: 'POST',
                    body: data
                  }).then(function(response) {
                    return response.text();
                  }).then((data) => {
                    if (data != 'ok') {
                        alert(data);
                    } else {
                        document.getElementsByClassName("buttonNext")[0].textContent = 'Installed';
                        document.getElementsByClassName("buttonPrevious")[0].style.display = 'none';
                        ok = true;
                    }

                    return ok;
                    });
            } else {
                return false;
            }
        }

        return true;
    }

    function isEmpty(id)
    {
        var el = document.getElementById(id);
        if (el && !el.value) {
            el.classList.add('error');
            return true;
        } else {
            el.classList.remove('error');
            return false;
        }
    }