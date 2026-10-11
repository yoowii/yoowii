/******/ (() => { // webpackBootstrap
/******/ 	"use strict";
/******/ 	var __webpack_modules__ = ({

/***/ "./themes/YoowiiTheme/assets/js/index.js"
/*!***********************************************!*\
  !*** ./themes/YoowiiTheme/assets/js/index.js ***!
  \***********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _sylius_toggle_theme__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./sylius-toggle-theme */ "./themes/YoowiiTheme/assets/js/sylius-toggle-theme.js");

document.addEventListener('DOMContentLoaded', () => {
  // Toggle themes
  (0,_sylius_toggle_theme__WEBPACK_IMPORTED_MODULE_0__.SyliusToggleTheme)();
});

// Apply theme
(0,_sylius_toggle_theme__WEBPACK_IMPORTED_MODULE_0__.SyliusApplyTheme)();

/***/ },

/***/ "./themes/YoowiiTheme/assets/js/sylius-toggle-theme.js"
/*!*************************************************************!*\
  !*** ./themes/YoowiiTheme/assets/js/sylius-toggle-theme.js ***!
  \*************************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
/* harmony export */ __webpack_require__.d(__webpack_exports__, {
/* harmony export */   SyliusApplyTheme: () => (/* binding */ SyliusApplyTheme),
/* harmony export */   SyliusToggleTheme: () => (/* binding */ SyliusToggleTheme)
/* harmony export */ });
const SyliusToggleTheme = () => {
  const themeToggleInputs = document.querySelectorAll('.theme-controller');
  if (!themeToggleInputs) {
    return;
  }
  themeToggleInputs.forEach(themeToggleInput => {
    themeToggleInput.addEventListener('click', function (event) {
      const theme = event.target.value;
      localStorage.setItem('color-theme', theme);
    });
  });
};
const SyliusApplyTheme = () => {
  const savedTheme = localStorage.getItem('color-theme');
  if (savedTheme) {
    document.documentElement.setAttribute('data-theme', savedTheme);
    const activeInput = document.querySelector(`.theme-controller[value="${savedTheme}"]`);
    if (activeInput) {
      activeInput.checked = true;
    }
  } else if (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches) {
    //document.documentElement.setAttribute('data-theme', 'dark');
  }
};


/***/ },

/***/ "./node_modules/@fontsource/dm-sans/400.css"
/*!**************************************************!*\
  !*** ./node_modules/@fontsource/dm-sans/400.css ***!
  \**************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "./node_modules/@fontsource/dm-sans/500.css"
/*!**************************************************!*\
  !*** ./node_modules/@fontsource/dm-sans/500.css ***!
  \**************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "./node_modules/@fontsource/dm-sans/700.css"
/*!**************************************************!*\
  !*** ./node_modules/@fontsource/dm-sans/700.css ***!
  \**************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "./node_modules/@fontsource/sora/600.css"
/*!***********************************************!*\
  !*** ./node_modules/@fontsource/sora/600.css ***!
  \***********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "./node_modules/@fontsource/sora/800.css"
/*!***********************************************!*\
  !*** ./node_modules/@fontsource/sora/800.css ***!
  \***********************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "./themes/YoowiiTheme/assets/css/main.css"
/*!************************************************!*\
  !*** ./themes/YoowiiTheme/assets/css/main.css ***!
  \************************************************/
(__unused_webpack_module, __webpack_exports__, __webpack_require__) {

__webpack_require__.r(__webpack_exports__);
// extracted by mini-css-extract-plugin


/***/ },

/***/ "./themes/YoowiiTheme/assets/media/sylius-logo.png"
/*!*********************************************************!*\
  !*** ./themes/YoowiiTheme/assets/media/sylius-logo.png ***!
  \*********************************************************/
(module, __unused_webpack_exports, __webpack_require__) {

module.exports = __webpack_require__.p + "images/sylius-logo.72a9465e.png";

/***/ },

/***/ "./themes/YoowiiTheme/assets/media/sylius-plus.png"
/*!*********************************************************!*\
  !*** ./themes/YoowiiTheme/assets/media/sylius-plus.png ***!
  \*********************************************************/
(module, __unused_webpack_exports, __webpack_require__) {

module.exports = __webpack_require__.p + "images/sylius-plus.fff4fe72.png";

/***/ },

/***/ "./themes/YoowiiTheme/assets/media/sylius-plus.webp"
/*!**********************************************************!*\
  !*** ./themes/YoowiiTheme/assets/media/sylius-plus.webp ***!
  \**********************************************************/
(module, __unused_webpack_exports, __webpack_require__) {

module.exports = __webpack_require__.p + "images/sylius-plus.c362d87b.webp";

/***/ }

/******/ 	});
/************************************************************************/
/******/ 	// The module cache
/******/ 	const __webpack_module_cache__ = {};
/******/ 	
/******/ 	// The require function
/******/ 	function __webpack_require__(moduleId) {
/******/ 		// Check if module is in cache
/******/ 		const cachedModule = __webpack_module_cache__[moduleId];
/******/ 		if (cachedModule !== undefined) {
/******/ 			return cachedModule.exports;
/******/ 		}
/******/ 		// Create a new module (and put it into the cache)
/******/ 		const module = __webpack_module_cache__[moduleId] = {
/******/ 			// no module.id needed
/******/ 			// no module.loaded needed
/******/ 			exports: {}
/******/ 		};
/******/ 	
/******/ 		// Execute the module function
/******/ 		if (!(moduleId in __webpack_modules__)) {
/******/ 			delete __webpack_module_cache__[moduleId];
/******/ 			const e = new Error("Cannot find module '" + moduleId + "'");
/******/ 			e.code = 'MODULE_NOT_FOUND';
/******/ 			throw e;
/******/ 		}
/******/ 		__webpack_modules__[moduleId](module, module.exports, __webpack_require__);
/******/ 	
/******/ 		// Return the exports of the module
/******/ 		return module.exports;
/******/ 	}
/******/ 	
/************************************************************************/
/******/ 	/* webpack/runtime/define property getters */
/******/ 	(() => {
/******/ 		// define getter/value functions for harmony exports
/******/ 		__webpack_require__.d = (exports, definition) => {
/******/ 			if(Array.isArray(definition)) {
/******/ 				var i = 0;
/******/ 				while(i < definition.length) {
/******/ 					var key = definition[i++];
/******/ 					var binding = definition[i++];
/******/ 					if(!__webpack_require__.o(exports, key)) {
/******/ 						if(binding === 0) {
/******/ 							Object.defineProperty(exports, key, { enumerable: true, value: definition[i++] });
/******/ 						} else {
/******/ 							Object.defineProperty(exports, key, { enumerable: true, get: binding });
/******/ 						}
/******/ 					} else if(binding === 0) { i++; }
/******/ 				}
/******/ 			} else {
/******/ 				for(var key in definition) {
/******/ 					if(__webpack_require__.o(definition, key) && !__webpack_require__.o(exports, key)) {
/******/ 						Object.defineProperty(exports, key, { enumerable: true, get: definition[key] });
/******/ 					}
/******/ 				}
/******/ 			}
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/hasOwnProperty shorthand */
/******/ 	(() => {
/******/ 		__webpack_require__.o = (obj, prop) => (Object.prototype.hasOwnProperty.call(obj, prop))
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/make namespace object */
/******/ 	(() => {
/******/ 		// define __esModule on exports
/******/ 		__webpack_require__.r = (exports) => {
/******/ 			if(Symbol.toStringTag) {
/******/ 				Object.defineProperty(exports, Symbol.toStringTag, { value: 'Module' });
/******/ 			}
/******/ 			Object.defineProperty(exports, '__esModule', { value: true });
/******/ 		};
/******/ 	})();
/******/ 	
/******/ 	/* webpack/runtime/publicPath */
/******/ 	(() => {
/******/ 		__webpack_require__.p = "/themes/yoowii-theme/";
/******/ 	})();
/******/ 	
/************************************************************************/
let __webpack_exports__ = {};
// This entry needs to be wrapped in an IIFE because it needs to be isolated against other modules in the chunk.
(() => {
/*!********************************************!*\
  !*** ./themes/YoowiiTheme/assets/index.js ***!
  \********************************************/
__webpack_require__.r(__webpack_exports__);
/* harmony import */ var _js_index__WEBPACK_IMPORTED_MODULE_0__ = __webpack_require__(/*! ./js/index */ "./themes/YoowiiTheme/assets/js/index.js");
/* harmony import */ var _fontsource_dm_sans_400_css__WEBPACK_IMPORTED_MODULE_1__ = __webpack_require__(/*! @fontsource/dm-sans/400.css */ "./node_modules/@fontsource/dm-sans/400.css");
/* harmony import */ var _fontsource_dm_sans_500_css__WEBPACK_IMPORTED_MODULE_2__ = __webpack_require__(/*! @fontsource/dm-sans/500.css */ "./node_modules/@fontsource/dm-sans/500.css");
/* harmony import */ var _fontsource_dm_sans_700_css__WEBPACK_IMPORTED_MODULE_3__ = __webpack_require__(/*! @fontsource/dm-sans/700.css */ "./node_modules/@fontsource/dm-sans/700.css");
/* harmony import */ var _fontsource_sora_600_css__WEBPACK_IMPORTED_MODULE_4__ = __webpack_require__(/*! @fontsource/sora/600.css */ "./node_modules/@fontsource/sora/600.css");
/* harmony import */ var _fontsource_sora_800_css__WEBPACK_IMPORTED_MODULE_5__ = __webpack_require__(/*! @fontsource/sora/800.css */ "./node_modules/@fontsource/sora/800.css");
/* harmony import */ var _css_main_css__WEBPACK_IMPORTED_MODULE_6__ = __webpack_require__(/*! ./css/main.css */ "./themes/YoowiiTheme/assets/css/main.css");
/* harmony import */ var _media_sylius_logo_png__WEBPACK_IMPORTED_MODULE_7__ = __webpack_require__(/*! ./media/sylius-logo.png */ "./themes/YoowiiTheme/assets/media/sylius-logo.png");
/* harmony import */ var _media_sylius_plus_png__WEBPACK_IMPORTED_MODULE_8__ = __webpack_require__(/*! ./media/sylius-plus.png */ "./themes/YoowiiTheme/assets/media/sylius-plus.png");
/* harmony import */ var _media_sylius_plus_webp__WEBPACK_IMPORTED_MODULE_9__ = __webpack_require__(/*! ./media/sylius-plus.webp */ "./themes/YoowiiTheme/assets/media/sylius-plus.webp");
// Main scripts file


// Local brand fonts. Fontsource emits WOFF2 assets with font-display: swap,
// keeping first render independent from a third-party font request.






// Main styles file




})();

/******/ })()
;
//# sourceMappingURL=data:application/json;charset=utf-8;base64,eyJ2ZXJzaW9uIjozLCJmaWxlIjoiYXBwLmpzIiwibWFwcGluZ3MiOiI7Ozs7Ozs7Ozs7OztBQUE0RTtBQUU1RUUsUUFBUSxDQUFDQyxnQkFBZ0IsQ0FBQyxrQkFBa0IsRUFBRSxNQUFNO0VBQ2hEO0VBQ0FILHVFQUFpQixDQUFDLENBQUM7QUFDdkIsQ0FBQyxDQUFDOztBQUVGO0FBQ0FDLHNFQUFnQixDQUFDLENBQUMsQzs7Ozs7Ozs7Ozs7Ozs7O0FDUmxCLE1BQU1ELGlCQUFpQixHQUFHQSxDQUFBLEtBQU07RUFDOUIsTUFBTUksaUJBQWlCLEdBQUdGLFFBQVEsQ0FBQ0csZ0JBQWdCLENBQUMsbUJBQW1CLENBQUM7RUFDeEUsSUFBSSxDQUFDRCxpQkFBaUIsRUFBRTtJQUN0QjtFQUNGO0VBQ0FBLGlCQUFpQixDQUFDRSxPQUFPLENBQUVDLGdCQUFnQixJQUFLO0lBQzlDQSxnQkFBZ0IsQ0FBQ0osZ0JBQWdCLENBQUMsT0FBTyxFQUFFLFVBQVNLLEtBQUssRUFBRTtNQUN6RCxNQUFNQyxLQUFLLEdBQUdELEtBQUssQ0FBQ0UsTUFBTSxDQUFDQyxLQUFLO01BQ2hDQyxZQUFZLENBQUNDLE9BQU8sQ0FBQyxhQUFhLEVBQUVKLEtBQUssQ0FBQztJQUM1QyxDQUFDLENBQUM7RUFDSixDQUFDLENBQUM7QUFDSixDQUFDO0FBRUQsTUFBTVIsZ0JBQWdCLEdBQUdBLENBQUEsS0FBTTtFQUM3QixNQUFNYSxVQUFVLEdBQUdGLFlBQVksQ0FBQ0csT0FBTyxDQUFDLGFBQWEsQ0FBQztFQUN0RCxJQUFJRCxVQUFVLEVBQUU7SUFDZFosUUFBUSxDQUFDYyxlQUFlLENBQUNDLFlBQVksQ0FBQyxZQUFZLEVBQUVILFVBQVUsQ0FBQztJQUMvRCxNQUFNSSxXQUFXLEdBQUdoQixRQUFRLENBQUNpQixhQUFhLENBQUMsNEJBQTRCTCxVQUFVLElBQUksQ0FBQztJQUN0RixJQUFJSSxXQUFXLEVBQUU7TUFDZkEsV0FBVyxDQUFDRSxPQUFPLEdBQUcsSUFBSTtJQUM1QjtFQUNGLENBQUMsTUFBTSxJQUFJLEVBQUUsYUFBYSxJQUFJUixZQUFZLENBQUMsSUFBSVMsTUFBTSxDQUFDQyxVQUFVLENBQUMsOEJBQThCLENBQUMsQ0FBQ0MsT0FBTyxFQUFFO0lBQ3hHO0VBQUE7QUFFSixDQUFDOzs7Ozs7Ozs7Ozs7QUN4QkQ7Ozs7Ozs7Ozs7OztBQ0FBOzs7Ozs7Ozs7Ozs7QUNBQTs7Ozs7Ozs7Ozs7O0FDQUE7Ozs7Ozs7Ozs7OztBQ0FBOzs7Ozs7Ozs7Ozs7QUNBQTs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7OztVQ0FBO1VBQ0E7O1VBRUE7VUFDQTtVQUNBO1VBQ0E7VUFDQTtVQUNBO1VBQ0E7VUFDQTtVQUNBO1VBQ0E7VUFDQTtVQUNBO1VBQ0E7O1VBRUE7VUFDQTtVQUNBO1VBQ0E7VUFDQTtVQUNBO1VBQ0E7VUFDQTs7VUFFQTtVQUNBO1VBQ0E7Ozs7O1dDNUJBO1dBQ0E7V0FDQTtXQUNBO1dBQ0E7V0FDQTtXQUNBO1dBQ0E7V0FDQTtXQUNBLDJDQUEyQywwQ0FBMEM7V0FDckYsTUFBTTtXQUNOLDJDQUEyQyxnQ0FBZ0M7V0FDM0U7V0FDQSxLQUFLLHlCQUF5QjtXQUM5QjtXQUNBLEdBQUc7V0FDSDtXQUNBO1dBQ0EsMENBQTBDLHdDQUF3QztXQUNsRjtXQUNBO1dBQ0E7V0FDQSxFOzs7OztXQ3RCQSx3Rjs7Ozs7V0NBQTtXQUNBO1dBQ0E7V0FDQSx1REFBdUQsaUJBQWlCO1dBQ3hFO1dBQ0EsZ0RBQWdELGFBQWE7V0FDN0QsRTs7Ozs7V0NOQSxnRDs7Ozs7Ozs7Ozs7Ozs7Ozs7Ozs7O0FDQUE7QUFDb0I7O0FBRXBCO0FBQ0E7QUFDcUM7QUFDQTtBQUNBO0FBQ0g7QUFDQTs7QUFFbEM7QUFDd0I7QUFFUztBQUNBIiwic291cmNlcyI6WyJ3ZWJwYWNrOi8vLy4vdGhlbWVzL1lvb3dpaVRoZW1lL2Fzc2V0cy9qcy9pbmRleC5qcyIsIndlYnBhY2s6Ly8vLi90aGVtZXMvWW9vd2lpVGhlbWUvYXNzZXRzL2pzL3N5bGl1cy10b2dnbGUtdGhlbWUuanMiLCJ3ZWJwYWNrOi8vLy4vbm9kZV9tb2R1bGVzL0Bmb250c291cmNlL2RtLXNhbnMvNDAwLmNzcz9kYmFkIiwid2VicGFjazovLy8uL25vZGVfbW9kdWxlcy9AZm9udHNvdXJjZS9kbS1zYW5zLzUwMC5jc3M/ZTMzOSIsIndlYnBhY2s6Ly8vLi9ub2RlX21vZHVsZXMvQGZvbnRzb3VyY2UvZG0tc2Fucy83MDAuY3NzPzk1ZDIiLCJ3ZWJwYWNrOi8vLy4vbm9kZV9tb2R1bGVzL0Bmb250c291cmNlL3NvcmEvNjAwLmNzcz8xOGZmIiwid2VicGFjazovLy8uL25vZGVfbW9kdWxlcy9AZm9udHNvdXJjZS9zb3JhLzgwMC5jc3M/YmZiMSIsIndlYnBhY2s6Ly8vLi90aGVtZXMvWW9vd2lpVGhlbWUvYXNzZXRzL2Nzcy9tYWluLmNzcz8wNDg1Iiwid2VicGFjazovLy93ZWJwYWNrL2Jvb3RzdHJhcCIsIndlYnBhY2s6Ly8vd2VicGFjay9ydW50aW1lL2RlZmluZSBwcm9wZXJ0eSBnZXR0ZXJzIiwid2VicGFjazovLy93ZWJwYWNrL3J1bnRpbWUvaGFzT3duUHJvcGVydHkgc2hvcnRoYW5kIiwid2VicGFjazovLy93ZWJwYWNrL3J1bnRpbWUvbWFrZSBuYW1lc3BhY2Ugb2JqZWN0Iiwid2VicGFjazovLy93ZWJwYWNrL3J1bnRpbWUvcHVibGljUGF0aCIsIndlYnBhY2s6Ly8vLi90aGVtZXMvWW9vd2lpVGhlbWUvYXNzZXRzL2luZGV4LmpzIl0sInNvdXJjZXNDb250ZW50IjpbImltcG9ydCB7IFN5bGl1c1RvZ2dsZVRoZW1lLCBTeWxpdXNBcHBseVRoZW1lIH0gZnJvbSAnLi9zeWxpdXMtdG9nZ2xlLXRoZW1lJztcblxuZG9jdW1lbnQuYWRkRXZlbnRMaXN0ZW5lcignRE9NQ29udGVudExvYWRlZCcsICgpID0+IHtcbiAgICAvLyBUb2dnbGUgdGhlbWVzXG4gICAgU3lsaXVzVG9nZ2xlVGhlbWUoKTtcbn0pO1xuXG4vLyBBcHBseSB0aGVtZVxuU3lsaXVzQXBwbHlUaGVtZSgpO1xuIiwiY29uc3QgU3lsaXVzVG9nZ2xlVGhlbWUgPSAoKSA9PiB7XG4gIGNvbnN0IHRoZW1lVG9nZ2xlSW5wdXRzID0gZG9jdW1lbnQucXVlcnlTZWxlY3RvckFsbCgnLnRoZW1lLWNvbnRyb2xsZXInKTtcbiAgaWYgKCF0aGVtZVRvZ2dsZUlucHV0cykge1xuICAgIHJldHVybjtcbiAgfVxuICB0aGVtZVRvZ2dsZUlucHV0cy5mb3JFYWNoKCh0aGVtZVRvZ2dsZUlucHV0KSA9PiB7XG4gICAgdGhlbWVUb2dnbGVJbnB1dC5hZGRFdmVudExpc3RlbmVyKCdjbGljaycsIGZ1bmN0aW9uKGV2ZW50KSB7XG4gICAgICBjb25zdCB0aGVtZSA9IGV2ZW50LnRhcmdldC52YWx1ZTtcbiAgICAgIGxvY2FsU3RvcmFnZS5zZXRJdGVtKCdjb2xvci10aGVtZScsIHRoZW1lKTtcbiAgICB9KTtcbiAgfSk7XG59XG5cbmNvbnN0IFN5bGl1c0FwcGx5VGhlbWUgPSAoKSA9PiB7XG4gIGNvbnN0IHNhdmVkVGhlbWUgPSBsb2NhbFN0b3JhZ2UuZ2V0SXRlbSgnY29sb3ItdGhlbWUnKTtcbiAgaWYgKHNhdmVkVGhlbWUpIHtcbiAgICBkb2N1bWVudC5kb2N1bWVudEVsZW1lbnQuc2V0QXR0cmlidXRlKCdkYXRhLXRoZW1lJywgc2F2ZWRUaGVtZSk7XG4gICAgY29uc3QgYWN0aXZlSW5wdXQgPSBkb2N1bWVudC5xdWVyeVNlbGVjdG9yKGAudGhlbWUtY29udHJvbGxlclt2YWx1ZT1cIiR7c2F2ZWRUaGVtZX1cIl1gKTtcbiAgICBpZiAoYWN0aXZlSW5wdXQpIHtcbiAgICAgIGFjdGl2ZUlucHV0LmNoZWNrZWQgPSB0cnVlO1xuICAgIH1cbiAgfSBlbHNlIGlmICghKCdjb2xvci10aGVtZScgaW4gbG9jYWxTdG9yYWdlKSAmJiB3aW5kb3cubWF0Y2hNZWRpYSgnKHByZWZlcnMtY29sb3Itc2NoZW1lOiBkYXJrKScpLm1hdGNoZXMpIHtcbiAgICAvL2RvY3VtZW50LmRvY3VtZW50RWxlbWVudC5zZXRBdHRyaWJ1dGUoJ2RhdGEtdGhlbWUnLCAnZGFyaycpO1xuICB9XG59XG5cbmV4cG9ydCB7IFN5bGl1c1RvZ2dsZVRoZW1lLCBTeWxpdXNBcHBseVRoZW1lIH07XG4iLCIvLyBleHRyYWN0ZWQgYnkgbWluaS1jc3MtZXh0cmFjdC1wbHVnaW5cbmV4cG9ydCB7fTsiLCIvLyBleHRyYWN0ZWQgYnkgbWluaS1jc3MtZXh0cmFjdC1wbHVnaW5cbmV4cG9ydCB7fTsiLCIvLyBleHRyYWN0ZWQgYnkgbWluaS1jc3MtZXh0cmFjdC1wbHVnaW5cbmV4cG9ydCB7fTsiLCIvLyBleHRyYWN0ZWQgYnkgbWluaS1jc3MtZXh0cmFjdC1wbHVnaW5cbmV4cG9ydCB7fTsiLCIvLyBleHRyYWN0ZWQgYnkgbWluaS1jc3MtZXh0cmFjdC1wbHVnaW5cbmV4cG9ydCB7fTsiLCIvLyBleHRyYWN0ZWQgYnkgbWluaS1jc3MtZXh0cmFjdC1wbHVnaW5cbmV4cG9ydCB7fTsiLCIvLyBUaGUgbW9kdWxlIGNhY2hlXG5jb25zdCBfX3dlYnBhY2tfbW9kdWxlX2NhY2hlX18gPSB7fTtcblxuLy8gVGhlIHJlcXVpcmUgZnVuY3Rpb25cbmZ1bmN0aW9uIF9fd2VicGFja19yZXF1aXJlX18obW9kdWxlSWQpIHtcblx0Ly8gQ2hlY2sgaWYgbW9kdWxlIGlzIGluIGNhY2hlXG5cdGNvbnN0IGNhY2hlZE1vZHVsZSA9IF9fd2VicGFja19tb2R1bGVfY2FjaGVfX1ttb2R1bGVJZF07XG5cdGlmIChjYWNoZWRNb2R1bGUgIT09IHVuZGVmaW5lZCkge1xuXHRcdHJldHVybiBjYWNoZWRNb2R1bGUuZXhwb3J0cztcblx0fVxuXHQvLyBDcmVhdGUgYSBuZXcgbW9kdWxlIChhbmQgcHV0IGl0IGludG8gdGhlIGNhY2hlKVxuXHRjb25zdCBtb2R1bGUgPSBfX3dlYnBhY2tfbW9kdWxlX2NhY2hlX19bbW9kdWxlSWRdID0ge1xuXHRcdC8vIG5vIG1vZHVsZS5pZCBuZWVkZWRcblx0XHQvLyBubyBtb2R1bGUubG9hZGVkIG5lZWRlZFxuXHRcdGV4cG9ydHM6IHt9XG5cdH07XG5cblx0Ly8gRXhlY3V0ZSB0aGUgbW9kdWxlIGZ1bmN0aW9uXG5cdGlmICghKG1vZHVsZUlkIGluIF9fd2VicGFja19tb2R1bGVzX18pKSB7XG5cdFx0ZGVsZXRlIF9fd2VicGFja19tb2R1bGVfY2FjaGVfX1ttb2R1bGVJZF07XG5cdFx0Y29uc3QgZSA9IG5ldyBFcnJvcihcIkNhbm5vdCBmaW5kIG1vZHVsZSAnXCIgKyBtb2R1bGVJZCArIFwiJ1wiKTtcblx0XHRlLmNvZGUgPSAnTU9EVUxFX05PVF9GT1VORCc7XG5cdFx0dGhyb3cgZTtcblx0fVxuXHRfX3dlYnBhY2tfbW9kdWxlc19fW21vZHVsZUlkXShtb2R1bGUsIG1vZHVsZS5leHBvcnRzLCBfX3dlYnBhY2tfcmVxdWlyZV9fKTtcblxuXHQvLyBSZXR1cm4gdGhlIGV4cG9ydHMgb2YgdGhlIG1vZHVsZVxuXHRyZXR1cm4gbW9kdWxlLmV4cG9ydHM7XG59XG5cbiIsIi8vIGRlZmluZSBnZXR0ZXIvdmFsdWUgZnVuY3Rpb25zIGZvciBoYXJtb255IGV4cG9ydHNcbl9fd2VicGFja19yZXF1aXJlX18uZCA9IChleHBvcnRzLCBkZWZpbml0aW9uKSA9PiB7XG5cdGlmKEFycmF5LmlzQXJyYXkoZGVmaW5pdGlvbikpIHtcblx0XHR2YXIgaSA9IDA7XG5cdFx0d2hpbGUoaSA8IGRlZmluaXRpb24ubGVuZ3RoKSB7XG5cdFx0XHR2YXIga2V5ID0gZGVmaW5pdGlvbltpKytdO1xuXHRcdFx0dmFyIGJpbmRpbmcgPSBkZWZpbml0aW9uW2krK107XG5cdFx0XHRpZighX193ZWJwYWNrX3JlcXVpcmVfXy5vKGV4cG9ydHMsIGtleSkpIHtcblx0XHRcdFx0aWYoYmluZGluZyA9PT0gMCkge1xuXHRcdFx0XHRcdE9iamVjdC5kZWZpbmVQcm9wZXJ0eShleHBvcnRzLCBrZXksIHsgZW51bWVyYWJsZTogdHJ1ZSwgdmFsdWU6IGRlZmluaXRpb25baSsrXSB9KTtcblx0XHRcdFx0fSBlbHNlIHtcblx0XHRcdFx0XHRPYmplY3QuZGVmaW5lUHJvcGVydHkoZXhwb3J0cywga2V5LCB7IGVudW1lcmFibGU6IHRydWUsIGdldDogYmluZGluZyB9KTtcblx0XHRcdFx0fVxuXHRcdFx0fSBlbHNlIGlmKGJpbmRpbmcgPT09IDApIHsgaSsrOyB9XG5cdFx0fVxuXHR9IGVsc2Uge1xuXHRcdGZvcih2YXIga2V5IGluIGRlZmluaXRpb24pIHtcblx0XHRcdGlmKF9fd2VicGFja19yZXF1aXJlX18ubyhkZWZpbml0aW9uLCBrZXkpICYmICFfX3dlYnBhY2tfcmVxdWlyZV9fLm8oZXhwb3J0cywga2V5KSkge1xuXHRcdFx0XHRPYmplY3QuZGVmaW5lUHJvcGVydHkoZXhwb3J0cywga2V5LCB7IGVudW1lcmFibGU6IHRydWUsIGdldDogZGVmaW5pdGlvbltrZXldIH0pO1xuXHRcdFx0fVxuXHRcdH1cblx0fVxufTsiLCJfX3dlYnBhY2tfcmVxdWlyZV9fLm8gPSAob2JqLCBwcm9wKSA9PiAoT2JqZWN0LnByb3RvdHlwZS5oYXNPd25Qcm9wZXJ0eS5jYWxsKG9iaiwgcHJvcCkpIiwiLy8gZGVmaW5lIF9fZXNNb2R1bGUgb24gZXhwb3J0c1xuX193ZWJwYWNrX3JlcXVpcmVfXy5yID0gKGV4cG9ydHMpID0+IHtcblx0aWYoU3ltYm9sLnRvU3RyaW5nVGFnKSB7XG5cdFx0T2JqZWN0LmRlZmluZVByb3BlcnR5KGV4cG9ydHMsIFN5bWJvbC50b1N0cmluZ1RhZywgeyB2YWx1ZTogJ01vZHVsZScgfSk7XG5cdH1cblx0T2JqZWN0LmRlZmluZVByb3BlcnR5KGV4cG9ydHMsICdfX2VzTW9kdWxlJywgeyB2YWx1ZTogdHJ1ZSB9KTtcbn07IiwiX193ZWJwYWNrX3JlcXVpcmVfXy5wID0gXCIvdGhlbWVzL3lvb3dpaS10aGVtZS9cIjsiLCIvLyBNYWluIHNjcmlwdHMgZmlsZVxuaW1wb3J0ICcuL2pzL2luZGV4JztcblxuLy8gTG9jYWwgYnJhbmQgZm9udHMuIEZvbnRzb3VyY2UgZW1pdHMgV09GRjIgYXNzZXRzIHdpdGggZm9udC1kaXNwbGF5OiBzd2FwLFxuLy8ga2VlcGluZyBmaXJzdCByZW5kZXIgaW5kZXBlbmRlbnQgZnJvbSBhIHRoaXJkLXBhcnR5IGZvbnQgcmVxdWVzdC5cbmltcG9ydCAnQGZvbnRzb3VyY2UvZG0tc2Fucy80MDAuY3NzJztcbmltcG9ydCAnQGZvbnRzb3VyY2UvZG0tc2Fucy81MDAuY3NzJztcbmltcG9ydCAnQGZvbnRzb3VyY2UvZG0tc2Fucy83MDAuY3NzJztcbmltcG9ydCAnQGZvbnRzb3VyY2Uvc29yYS82MDAuY3NzJztcbmltcG9ydCAnQGZvbnRzb3VyY2Uvc29yYS84MDAuY3NzJztcblxuLy8gTWFpbiBzdHlsZXMgZmlsZVxuaW1wb3J0ICcuL2Nzcy9tYWluLmNzcyc7XG5cbmltcG9ydCAnLi9tZWRpYS9zeWxpdXMtbG9nby5wbmcnO1xuaW1wb3J0ICcuL21lZGlhL3N5bGl1cy1wbHVzLnBuZyc7XG5pbXBvcnQgJy4vbWVkaWEvc3lsaXVzLXBsdXMud2VicCc7XG4iXSwibmFtZXMiOlsiU3lsaXVzVG9nZ2xlVGhlbWUiLCJTeWxpdXNBcHBseVRoZW1lIiwiZG9jdW1lbnQiLCJhZGRFdmVudExpc3RlbmVyIiwidGhlbWVUb2dnbGVJbnB1dHMiLCJxdWVyeVNlbGVjdG9yQWxsIiwiZm9yRWFjaCIsInRoZW1lVG9nZ2xlSW5wdXQiLCJldmVudCIsInRoZW1lIiwidGFyZ2V0IiwidmFsdWUiLCJsb2NhbFN0b3JhZ2UiLCJzZXRJdGVtIiwic2F2ZWRUaGVtZSIsImdldEl0ZW0iLCJkb2N1bWVudEVsZW1lbnQiLCJzZXRBdHRyaWJ1dGUiLCJhY3RpdmVJbnB1dCIsInF1ZXJ5U2VsZWN0b3IiLCJjaGVja2VkIiwid2luZG93IiwibWF0Y2hNZWRpYSIsIm1hdGNoZXMiXSwic291cmNlUm9vdCI6IiJ9