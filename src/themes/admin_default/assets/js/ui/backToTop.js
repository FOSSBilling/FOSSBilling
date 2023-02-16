/**
 * JavaScript for the "Back to Top" button.
 *
 * @copyright FOSSBilling (https://www.fossbilling.org)
 * @license   Apache-2.0
 *
 * This source file is subject to the Apache-2.0 License that is bundled
 * with this source code in the file LICENSE
 */

export default function (options) {
  const defaults = {
    hiddenClass: 'hidden',
    visibleClass: 'visible',
    buttonID: 'back-to-top',
    minimum: 200,
  };

  const settings = {...defaults, ...options};

  if (document.getElementById(settings.buttonID)) {
    let toTopButton = document.getElementById(settings.buttonID);

    toTopButton.addEventListener("click", toTop);

    function toTop() {
      document.body.scrollTop = 0;
      document.documentElement.scrollTop = 0;
    }

    window.onscroll = function () {
      // If the page is scrolled more than the threshold, make the button visible
      if (document.body.scrollTop > settings.minimum || document.documentElement.scrollTop > settings.minimum) {
        toTopButton.classList.replace(settings.hiddenClass, settings.visibleClass);
      } else {
        toTopButton.classList.replace(settings.visibleClass, settings.hiddenClass);
      }
    };
  }
};
