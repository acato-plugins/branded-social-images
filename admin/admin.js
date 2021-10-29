/******/ (function() { // webpackBootstrap
/******/ 	"use strict";
var __webpack_exports__ = {};

;// CONCATENATED MODULE: ./node_modules/vanilla-picker/dist/vanilla-picker.mjs
var classCallCheck = function (instance, Constructor) {
  if (!(instance instanceof Constructor)) {
    throw new TypeError("Cannot call a class as a function");
  }
};

var createClass = function () {
  function defineProperties(target, props) {
    for (var i = 0; i < props.length; i++) {
      var descriptor = props[i];
      descriptor.enumerable = descriptor.enumerable || false;
      descriptor.configurable = true;
      if ("value" in descriptor) descriptor.writable = true;
      Object.defineProperty(target, descriptor.key, descriptor);
    }
  }

  return function (Constructor, protoProps, staticProps) {
    if (protoProps) defineProperties(Constructor.prototype, protoProps);
    if (staticProps) defineProperties(Constructor, staticProps);
    return Constructor;
  };
}();

var slicedToArray = function () {
  function sliceIterator(arr, i) {
    var _arr = [];
    var _n = true;
    var _d = false;
    var _e = undefined;

    try {
      for (var _i = arr[Symbol.iterator](), _s; !(_n = (_s = _i.next()).done); _n = true) {
        _arr.push(_s.value);

        if (i && _arr.length === i) break;
      }
    } catch (err) {
      _d = true;
      _e = err;
    } finally {
      try {
        if (!_n && _i["return"]) _i["return"]();
      } finally {
        if (_d) throw _e;
      }
    }

    return _arr;
  }

  return function (arr, i) {
    if (Array.isArray(arr)) {
      return arr;
    } else if (Symbol.iterator in Object(arr)) {
      return sliceIterator(arr, i);
    } else {
      throw new TypeError("Invalid attempt to destructure non-iterable instance");
    }
  };
}();

String.prototype.startsWith = String.prototype.startsWith || function (needle) {
    return this.indexOf(needle) === 0;
};
String.prototype.padStart = String.prototype.padStart || function (len, pad) {
    var str = this;while (str.length < len) {
        str = pad + str;
    }return str;
};

var colorNames = { cb: '0f8ff', tqw: 'aebd7', q: '-ffff', qmrn: '7fffd4', zr: '0ffff', bg: '5f5dc', bsq: 'e4c4', bck: '---', nch: 'ebcd', b: '--ff', bvt: '8a2be2', brwn: 'a52a2a', brw: 'deb887', ctb: '5f9ea0', hrt: '7fff-', chcT: 'd2691e', cr: '7f50', rnw: '6495ed', crns: '8dc', crms: 'dc143c', cn: '-ffff', Db: '--8b', Dcn: '-8b8b', Dgnr: 'b8860b', Dgr: 'a9a9a9', Dgrn: '-64-', Dkhk: 'bdb76b', Dmgn: '8b-8b', Dvgr: '556b2f', Drng: '8c-', Drch: '9932cc', Dr: '8b--', Dsmn: 'e9967a', Dsgr: '8fbc8f', DsTb: '483d8b', DsTg: '2f4f4f', Dtrq: '-ced1', Dvt: '94-d3', ppnk: '1493', pskb: '-bfff', mgr: '696969', grb: '1e90ff', rbrc: 'b22222', rwht: 'af0', stg: '228b22', chs: '-ff', gnsb: 'dcdcdc', st: '8f8ff', g: 'd7-', gnr: 'daa520', gr: '808080', grn: '-8-0', grnw: 'adff2f', hnw: '0fff0', htpn: '69b4', nnr: 'cd5c5c', ng: '4b-82', vr: '0', khk: '0e68c', vnr: 'e6e6fa', nrb: '0f5', wngr: '7cfc-', mnch: 'acd', Lb: 'add8e6', Lcr: '08080', Lcn: 'e0ffff', Lgnr: 'afad2', Lgr: 'd3d3d3', Lgrn: '90ee90', Lpnk: 'b6c1', Lsmn: 'a07a', Lsgr: '20b2aa', Lskb: '87cefa', LsTg: '778899', Lstb: 'b0c4de', Lw: 'e0', m: '-ff-', mgrn: '32cd32', nn: 'af0e6', mgnt: '-ff', mrn: '8--0', mqm: '66cdaa', mmb: '--cd', mmrc: 'ba55d3', mmpr: '9370db', msg: '3cb371', mmsT: '7b68ee', '': '-fa9a', mtr: '48d1cc', mmvt: 'c71585', mnLb: '191970', ntc: '5fffa', mstr: 'e4e1', mccs: 'e4b5', vjw: 'dead', nv: '--80', c: 'df5e6', v: '808-0', vrb: '6b8e23', rng: 'a5-', rngr: '45-', rch: 'da70d6', pgnr: 'eee8aa', pgrn: '98fb98', ptrq: 'afeeee', pvtr: 'db7093', ppwh: 'efd5', pchp: 'dab9', pr: 'cd853f', pnk: 'c0cb', pm: 'dda0dd', pwrb: 'b0e0e6', prp: '8-080', cc: '663399', r: '--', sbr: 'bc8f8f', rb: '4169e1', sbrw: '8b4513', smn: 'a8072', nbr: '4a460', sgrn: '2e8b57', ssh: '5ee', snn: 'a0522d', svr: 'c0c0c0', skb: '87ceeb', sTb: '6a5acd', sTgr: '708090', snw: 'afa', n: '-ff7f', stb: '4682b4', tn: 'd2b48c', t: '-8080', thst: 'd8bfd8', tmT: '6347', trqs: '40e0d0', vt: 'ee82ee', whT: '5deb3', wht: '', hts: '5f5f5', w: '-', wgrn: '9acd32' };

function printNum(num) {
    var decs = arguments.length > 1 && arguments[1] !== undefined ? arguments[1] : 1;

    var str = decs > 0 ? num.toFixed(decs).replace(/0+$/, '').replace(/\.$/, '') : num.toString();
    return str || '0';
}

var Color = function () {
    function Color(r, g, b, a) {
        classCallCheck(this, Color);


        var that = this;
        function parseString(input) {

            if (input.startsWith('hsl')) {
                var _input$match$map = input.match(/([\-\d\.e]+)/g).map(Number),
                    _input$match$map2 = slicedToArray(_input$match$map, 4),
                    h = _input$match$map2[0],
                    s = _input$match$map2[1],
                    l = _input$match$map2[2],
                    _a = _input$match$map2[3];

                if (_a === undefined) {
                    _a = 1;
                }

                h /= 360;
                s /= 100;
                l /= 100;
                that.hsla = [h, s, l, _a];
            } else if (input.startsWith('rgb')) {
                var _input$match$map3 = input.match(/([\-\d\.e]+)/g).map(Number),
                    _input$match$map4 = slicedToArray(_input$match$map3, 4),
                    _r = _input$match$map4[0],
                    _g = _input$match$map4[1],
                    _b = _input$match$map4[2],
                    _a2 = _input$match$map4[3];

                if (_a2 === undefined) {
                    _a2 = 1;
                }

                that.rgba = [_r, _g, _b, _a2];
            } else {
                if (input.startsWith('#')) {
                    that.rgba = Color.hexToRgb(input);
                } else {
                    that.rgba = Color.nameToRgb(input) || Color.hexToRgb(input);
                }
            }
        }

        if (r === undefined) ; else if (Array.isArray(r)) {
            this.rgba = r;
        } else if (b === undefined) {
            var color = r && '' + r;
            if (color) {
                parseString(color.toLowerCase());
            }
        } else {
            this.rgba = [r, g, b, a === undefined ? 1 : a];
        }
    }

    createClass(Color, [{
        key: 'printRGB',
        value: function printRGB(alpha) {
            var rgb = alpha ? this.rgba : this.rgba.slice(0, 3),
                vals = rgb.map(function (x, i) {
                return printNum(x, i === 3 ? 3 : 0);
            });

            return alpha ? 'rgba(' + vals + ')' : 'rgb(' + vals + ')';
        }
    }, {
        key: 'printHSL',
        value: function printHSL(alpha) {
            var mults = [360, 100, 100, 1],
                suff = ['', '%', '%', ''];

            var hsl = alpha ? this.hsla : this.hsla.slice(0, 3),
                vals = hsl.map(function (x, i) {
                return printNum(x * mults[i], i === 3 ? 3 : 1) + suff[i];
            });

            return alpha ? 'hsla(' + vals + ')' : 'hsl(' + vals + ')';
        }
    }, {
        key: 'printHex',
        value: function printHex(alpha) {
            var hex = this.hex;
            return alpha ? hex : hex.substring(0, 7);
        }
    }, {
        key: 'rgba',
        get: function get$$1() {
            if (this._rgba) {
                return this._rgba;
            }
            if (!this._hsla) {
                throw new Error('No color is set');
            }

            return this._rgba = Color.hslToRgb(this._hsla);
        },
        set: function set$$1(rgb) {
            if (rgb.length === 3) {
                rgb[3] = 1;
            }

            this._rgba = rgb;
            this._hsla = null;
        }
    }, {
        key: 'rgbString',
        get: function get$$1() {
            return this.printRGB();
        }
    }, {
        key: 'rgbaString',
        get: function get$$1() {
            return this.printRGB(true);
        }
    }, {
        key: 'hsla',
        get: function get$$1() {
            if (this._hsla) {
                return this._hsla;
            }
            if (!this._rgba) {
                throw new Error('No color is set');
            }

            return this._hsla = Color.rgbToHsl(this._rgba);
        },
        set: function set$$1(hsl) {
            if (hsl.length === 3) {
                hsl[3] = 1;
            }

            this._hsla = hsl;
            this._rgba = null;
        }
    }, {
        key: 'hslString',
        get: function get$$1() {
            return this.printHSL();
        }
    }, {
        key: 'hslaString',
        get: function get$$1() {
            return this.printHSL(true);
        }
    }, {
        key: 'hex',
        get: function get$$1() {
            var rgb = this.rgba,
                hex = rgb.map(function (x, i) {
                return i < 3 ? x.toString(16) : Math.round(x * 255).toString(16);
            });

            return '#' + hex.map(function (x) {
                return x.padStart(2, '0');
            }).join('');
        },
        set: function set$$1(hex) {
            this.rgba = Color.hexToRgb(hex);
        }
    }], [{
        key: 'hexToRgb',
        value: function hexToRgb(input) {

            var hex = (input.startsWith('#') ? input.slice(1) : input).replace(/^(\w{3})$/, '$1F').replace(/^(\w)(\w)(\w)(\w)$/, '$1$1$2$2$3$3$4$4').replace(/^(\w{6})$/, '$1FF');

            if (!hex.match(/^([0-9a-fA-F]{8})$/)) {
                throw new Error('Unknown hex color; ' + input);
            }

            var rgba = hex.match(/^(\w\w)(\w\w)(\w\w)(\w\w)$/).slice(1).map(function (x) {
                return parseInt(x, 16);
            });

            rgba[3] = rgba[3] / 255;
            return rgba;
        }
    }, {
        key: 'nameToRgb',
        value: function nameToRgb(input) {

            var hash = input.toLowerCase().replace('at', 'T').replace(/[aeiouyldf]/g, '').replace('ght', 'L').replace('rk', 'D').slice(-5, 4),
                hex = colorNames[hash];
            return hex === undefined ? hex : Color.hexToRgb(hex.replace(/\-/g, '00').padStart(6, 'f'));
        }
    }, {
        key: 'rgbToHsl',
        value: function rgbToHsl(_ref) {
            var _ref2 = slicedToArray(_ref, 4),
                r = _ref2[0],
                g = _ref2[1],
                b = _ref2[2],
                a = _ref2[3];

            r /= 255;
            g /= 255;
            b /= 255;

            var max = Math.max(r, g, b),
                min = Math.min(r, g, b);
            var h = void 0,
                s = void 0,
                l = (max + min) / 2;

            if (max === min) {
                h = s = 0;
            } else {
                var d = max - min;
                s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
                switch (max) {
                    case r:
                        h = (g - b) / d + (g < b ? 6 : 0);break;
                    case g:
                        h = (b - r) / d + 2;break;
                    case b:
                        h = (r - g) / d + 4;break;
                }

                h /= 6;
            }

            return [h, s, l, a];
        }
    }, {
        key: 'hslToRgb',
        value: function hslToRgb(_ref3) {
            var _ref4 = slicedToArray(_ref3, 4),
                h = _ref4[0],
                s = _ref4[1],
                l = _ref4[2],
                a = _ref4[3];

            var r = void 0,
                g = void 0,
                b = void 0;

            if (s === 0) {
                r = g = b = l;
            } else {
                var hue2rgb = function hue2rgb(p, q, t) {
                    if (t < 0) t += 1;
                    if (t > 1) t -= 1;
                    if (t < 1 / 6) return p + (q - p) * 6 * t;
                    if (t < 1 / 2) return q;
                    if (t < 2 / 3) return p + (q - p) * (2 / 3 - t) * 6;
                    return p;
                };

                var q = l < 0.5 ? l * (1 + s) : l + s - l * s,
                    p = 2 * l - q;

                r = hue2rgb(p, q, h + 1 / 3);
                g = hue2rgb(p, q, h);
                b = hue2rgb(p, q, h - 1 / 3);
            }

            var rgba = [r * 255, g * 255, b * 255].map(Math.round);
            rgba[3] = a;

            return rgba;
        }
    }]);
    return Color;
}();

var EventBucket = function () {
    function EventBucket() {
        classCallCheck(this, EventBucket);

        this._events = [];
    }

    createClass(EventBucket, [{
        key: 'add',
        value: function add(target, type, handler) {
            target.addEventListener(type, handler, false);
            this._events.push({
                target: target,
                type: type,
                handler: handler
            });
        }
    }, {
        key: 'remove',
        value: function remove(target, type, handler) {
            this._events = this._events.filter(function (e) {
                var isMatch = true;
                if (target && target !== e.target) {
                    isMatch = false;
                }
                if (type && type !== e.type) {
                    isMatch = false;
                }
                if (handler && handler !== e.handler) {
                    isMatch = false;
                }

                if (isMatch) {
                    EventBucket._doRemove(e.target, e.type, e.handler);
                }
                return !isMatch;
            });
        }
    }, {
        key: 'destroy',
        value: function destroy() {
            this._events.forEach(function (e) {
                return EventBucket._doRemove(e.target, e.type, e.handler);
            });
            this._events = [];
        }
    }], [{
        key: '_doRemove',
        value: function _doRemove(target, type, handler) {
            target.removeEventListener(type, handler, false);
        }
    }]);
    return EventBucket;
}();

function parseHTML(htmlString) {

    var div = document.createElement('div');
    div.innerHTML = htmlString;
    return div.firstElementChild;
}

function dragTrack(eventBucket, area, callback) {
    var dragging = false;

    function clamp(val, min, max) {
        return Math.max(min, Math.min(val, max));
    }

    function onMove(e, info, starting) {
        if (starting) {
            dragging = true;
        }
        if (!dragging) {
            return;
        }

        e.preventDefault();

        var bounds = area.getBoundingClientRect(),
            w = bounds.width,
            h = bounds.height,
            x = info.clientX,
            y = info.clientY;

        var relX = clamp(x - bounds.left, 0, w),
            relY = clamp(y - bounds.top, 0, h);

        callback(relX / w, relY / h);
    }

    function onMouse(e, starting) {
        var button = e.buttons === undefined ? e.which : e.buttons;
        if (button === 1) {
            onMove(e, e, starting);
        } else {
            dragging = false;
        }
    }

    function onTouch(e, starting) {
        if (e.touches.length === 1) {
            onMove(e, e.touches[0], starting);
        } else {
            dragging = false;
        }
    }

    eventBucket.add(area, 'mousedown', function (e) {
        onMouse(e, true);
    });
    eventBucket.add(area, 'touchstart', function (e) {
        onTouch(e, true);
    });
    eventBucket.add(window, 'mousemove', onMouse);
    eventBucket.add(area, 'touchmove', onTouch);
    eventBucket.add(window, 'mouseup', function (e) {
        dragging = false;
    });
    eventBucket.add(area, 'touchend', function (e) {
        dragging = false;
    });
    eventBucket.add(area, 'touchcancel', function (e) {
        dragging = false;
    });
}

var BG_TRANSP = 'url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'2\' height=\'2\'%3E%3Cpath d=\'M1,0H0V1H2V2H1\' fill=\'lightgrey\'/%3E%3C/svg%3E")';
var HUES = 360;

var EVENT_KEY = 'keydown',
    EVENT_CLICK_OUTSIDE = 'mousedown',
    EVENT_TAB_MOVE = 'focusin';

function $(selector, context) {
    return (context || document).querySelector(selector);
}

function stopEvent(e) {

    e.preventDefault();
    e.stopPropagation();
}
function onKey(bucket, target, keys, handler, stop) {
    bucket.add(target, EVENT_KEY, function (e) {
        if (keys.indexOf(e.key) >= 0) {
            if (stop) {
                stopEvent(e);
            }
            handler(e);
        }
    });
}

var _style = document.createElement('style');
_style.textContent = '.picker_wrapper.no_alpha .picker_alpha{display:none}.picker_wrapper.no_editor .picker_editor{position:absolute;z-index:-1;opacity:0}.picker_wrapper.no_cancel .picker_cancel{display:none}.layout_default.picker_wrapper{display:-webkit-box;display:flex;-webkit-box-orient:horizontal;-webkit-box-direction:normal;flex-flow:row wrap;-webkit-box-pack:justify;justify-content:space-between;-webkit-box-align:stretch;align-items:stretch;font-size:10px;width:25em;padding:.5em}.layout_default.picker_wrapper input,.layout_default.picker_wrapper button{font-size:1rem}.layout_default.picker_wrapper>*{margin:.5em}.layout_default.picker_wrapper::before{content:\'\';display:block;width:100%;height:0;-webkit-box-ordinal-group:2;order:1}.layout_default .picker_slider,.layout_default .picker_selector{padding:1em}.layout_default .picker_hue{width:100%}.layout_default .picker_sl{-webkit-box-flex:1;flex:1 1 auto}.layout_default .picker_sl::before{content:\'\';display:block;padding-bottom:100%}.layout_default .picker_editor{-webkit-box-ordinal-group:2;order:1;width:6.5rem}.layout_default .picker_editor input{width:100%;height:100%}.layout_default .picker_sample{-webkit-box-ordinal-group:2;order:1;-webkit-box-flex:1;flex:1 1 auto}.layout_default .picker_done,.layout_default .picker_cancel{-webkit-box-ordinal-group:2;order:1}.picker_wrapper{box-sizing:border-box;background:#f2f2f2;box-shadow:0 0 0 1px silver;cursor:default;font-family:sans-serif;color:#444;pointer-events:auto}.picker_wrapper:focus{outline:none}.picker_wrapper button,.picker_wrapper input{box-sizing:border-box;border:none;box-shadow:0 0 0 1px silver;outline:none}.picker_wrapper button:focus,.picker_wrapper button:active,.picker_wrapper input:focus,.picker_wrapper input:active{box-shadow:0 0 2px 1px dodgerblue}.picker_wrapper button{padding:.4em .6em;cursor:pointer;background-color:whitesmoke;background-image:-webkit-gradient(linear, left bottom, left top, from(gainsboro), to(transparent));background-image:linear-gradient(0deg, gainsboro, transparent)}.picker_wrapper button:active{background-image:-webkit-gradient(linear, left bottom, left top, from(transparent), to(gainsboro));background-image:linear-gradient(0deg, transparent, gainsboro)}.picker_wrapper button:hover{background-color:white}.picker_selector{position:absolute;z-index:1;display:block;-webkit-transform:translate(-50%, -50%);transform:translate(-50%, -50%);border:2px solid white;border-radius:100%;box-shadow:0 0 3px 1px #67b9ff;background:currentColor;cursor:pointer}.picker_slider .picker_selector{border-radius:2px}.picker_hue{position:relative;background-image:-webkit-gradient(linear, left top, right top, from(red), color-stop(yellow), color-stop(lime), color-stop(cyan), color-stop(blue), color-stop(magenta), to(red));background-image:linear-gradient(90deg, red, yellow, lime, cyan, blue, magenta, red);box-shadow:0 0 0 1px silver}.picker_sl{position:relative;box-shadow:0 0 0 1px silver;background-image:-webkit-gradient(linear, left top, left bottom, from(white), color-stop(50%, rgba(255,255,255,0))),-webkit-gradient(linear, left bottom, left top, from(black), color-stop(50%, rgba(0,0,0,0))),-webkit-gradient(linear, left top, right top, from(gray), to(rgba(128,128,128,0)));background-image:linear-gradient(180deg, white, rgba(255,255,255,0) 50%),linear-gradient(0deg, black, rgba(0,0,0,0) 50%),linear-gradient(90deg, gray, rgba(128,128,128,0))}.picker_alpha,.picker_sample{position:relative;background:url("data:image/svg+xml,%3Csvg xmlns=\'http://www.w3.org/2000/svg\' width=\'2\' height=\'2\'%3E%3Cpath d=\'M1,0H0V1H2V2H1\' fill=\'lightgrey\'/%3E%3C/svg%3E") left top/contain white;box-shadow:0 0 0 1px silver}.picker_alpha .picker_selector,.picker_sample .picker_selector{background:none}.picker_editor input{font-family:monospace;padding:.2em .4em}.picker_sample::before{content:\'\';position:absolute;display:block;width:100%;height:100%;background:currentColor}.picker_arrow{position:absolute;z-index:-1}.picker_wrapper.popup{position:absolute;z-index:2;margin:1.5em}.picker_wrapper.popup,.picker_wrapper.popup .picker_arrow::before,.picker_wrapper.popup .picker_arrow::after{background:#f2f2f2;box-shadow:0 0 10px 1px rgba(0,0,0,0.4)}.picker_wrapper.popup .picker_arrow{width:3em;height:3em;margin:0}.picker_wrapper.popup .picker_arrow::before,.picker_wrapper.popup .picker_arrow::after{content:"";display:block;position:absolute;top:0;left:0;z-index:-99}.picker_wrapper.popup .picker_arrow::before{width:100%;height:100%;-webkit-transform:skew(45deg);transform:skew(45deg);-webkit-transform-origin:0 100%;transform-origin:0 100%}.picker_wrapper.popup .picker_arrow::after{width:150%;height:150%;box-shadow:none}.popup.popup_top{bottom:100%;left:0}.popup.popup_top .picker_arrow{bottom:0;left:0;-webkit-transform:rotate(-90deg);transform:rotate(-90deg)}.popup.popup_bottom{top:100%;left:0}.popup.popup_bottom .picker_arrow{top:0;left:0;-webkit-transform:rotate(90deg) scale(1, -1);transform:rotate(90deg) scale(1, -1)}.popup.popup_left{top:0;right:100%}.popup.popup_left .picker_arrow{top:0;right:0;-webkit-transform:scale(-1, 1);transform:scale(-1, 1)}.popup.popup_right{top:0;left:100%}.popup.popup_right .picker_arrow{top:0;left:0}';
document.documentElement.firstElementChild.appendChild(_style);

var Picker = function () {
    function Picker(options) {
        classCallCheck(this, Picker);


        this.settings = {

            popup: 'right',
            layout: 'default',
            alpha: true,
            editor: true,
            editorFormat: 'hex',
            cancelButton: false,
            defaultColor: '#0cf'
        };

        this._events = new EventBucket();

        this.onChange = null;

        this.onDone = null;

        this.onOpen = null;

        this.onClose = null;

        this.setOptions(options);
    }

    createClass(Picker, [{
        key: 'setOptions',
        value: function setOptions(options) {
            var _this = this;

            if (!options) {
                return;
            }
            var settings = this.settings;

            function transfer(source, target, skipKeys) {
                for (var key in source) {
                    if (skipKeys && skipKeys.indexOf(key) >= 0) {
                        continue;
                    }

                    target[key] = source[key];
                }
            }

            if (options instanceof HTMLElement) {
                settings.parent = options;
            } else {

                if (settings.parent && options.parent && settings.parent !== options.parent) {
                    this._events.remove(settings.parent);
                    this._popupInited = false;
                }

                transfer(options, settings);

                if (options.onChange) {
                    this.onChange = options.onChange;
                }
                if (options.onDone) {
                    this.onDone = options.onDone;
                }
                if (options.onOpen) {
                    this.onOpen = options.onOpen;
                }
                if (options.onClose) {
                    this.onClose = options.onClose;
                }

                var col = options.color || options.colour;
                if (col) {
                    this._setColor(col);
                }
            }

            var parent = settings.parent;
            if (parent && settings.popup && !this._popupInited) {

                var openProxy = function openProxy(e) {
                    return _this.openHandler(e);
                };

                this._events.add(parent, 'click', openProxy);

                onKey(this._events, parent, [' ', 'Spacebar', 'Enter'], openProxy);

                this._popupInited = true;
            } else if (options.parent && !settings.popup) {
                this.show();
            }
        }
    }, {
        key: 'openHandler',
        value: function openHandler(e) {
            if (this.show()) {

                e && e.preventDefault();

                this.settings.parent.style.pointerEvents = 'none';

                var toFocus = e && e.type === EVENT_KEY ? this._domEdit : this.domElement;
                setTimeout(function () {
                    return toFocus.focus();
                }, 100);

                if (this.onOpen) {
                    this.onOpen(this.colour);
                }
            }
        }
    }, {
        key: 'closeHandler',
        value: function closeHandler(e) {
            var event = e && e.type;
            var doHide = false;

            if (!e) {
                doHide = true;
            } else if (event === EVENT_CLICK_OUTSIDE || event === EVENT_TAB_MOVE) {

                var knownTime = (this.__containedEvent || 0) + 100;
                if (e.timeStamp > knownTime) {
                    doHide = true;
                }
            } else {

                stopEvent(e);

                doHide = true;
            }

            if (doHide && this.hide()) {
                this.settings.parent.style.pointerEvents = '';

                if (event !== EVENT_CLICK_OUTSIDE) {
                    this.settings.parent.focus();
                }

                if (this.onClose) {
                    this.onClose(this.colour);
                }
            }
        }
    }, {
        key: 'movePopup',
        value: function movePopup(options, open) {

            this.closeHandler();

            this.setOptions(options);
            if (open) {
                this.openHandler();
            }
        }
    }, {
        key: 'setColor',
        value: function setColor(color, silent) {
            this._setColor(color, { silent: silent });
        }
    }, {
        key: '_setColor',
        value: function _setColor(color, flags) {
            if (typeof color === 'string') {
                color = color.trim();
            }
            if (!color) {
                return;
            }

            flags = flags || {};
            var c = void 0;
            try {

                c = new Color(color);
            } catch (ex) {
                if (flags.failSilently) {
                    return;
                }
                throw ex;
            }

            if (!this.settings.alpha) {
                var hsla = c.hsla;
                hsla[3] = 1;
                c.hsla = hsla;
            }
            this.colour = this.color = c;
            this._setHSLA(null, null, null, null, flags);
        }
    }, {
        key: 'setColour',
        value: function setColour(colour, silent) {
            this.setColor(colour, silent);
        }
    }, {
        key: 'show',
        value: function show() {
            var parent = this.settings.parent;
            if (!parent) {
                return false;
            }

            if (this.domElement) {
                var toggled = this._toggleDOM(true);

                this._setPosition();

                return toggled;
            }

            var html = this.settings.template || '<div class="picker_wrapper" tabindex="-1"><div class="picker_arrow"></div><div class="picker_hue picker_slider"><div class="picker_selector"></div></div><div class="picker_sl"><div class="picker_selector"></div></div><div class="picker_alpha picker_slider"><div class="picker_selector"></div></div><div class="picker_editor"><input aria-label="Type a color name or hex value"/></div><div class="picker_sample"></div><div class="picker_done"><button>Ok</button></div><div class="picker_cancel"><button>Cancel</button></div></div>';
            var wrapper = parseHTML(html);

            this.domElement = wrapper;
            this._domH = $('.picker_hue', wrapper);
            this._domSL = $('.picker_sl', wrapper);
            this._domA = $('.picker_alpha', wrapper);
            this._domEdit = $('.picker_editor input', wrapper);
            this._domSample = $('.picker_sample', wrapper);
            this._domOkay = $('.picker_done button', wrapper);
            this._domCancel = $('.picker_cancel button', wrapper);

            wrapper.classList.add('layout_' + this.settings.layout);
            if (!this.settings.alpha) {
                wrapper.classList.add('no_alpha');
            }
            if (!this.settings.editor) {
                wrapper.classList.add('no_editor');
            }
            if (!this.settings.cancelButton) {
                wrapper.classList.add('no_cancel');
            }
            this._ifPopup(function () {
                return wrapper.classList.add('popup');
            });

            this._setPosition();

            if (this.colour) {
                this._updateUI();
            } else {
                this._setColor(this.settings.defaultColor);
            }
            this._bindEvents();

            return true;
        }
    }, {
        key: 'hide',
        value: function hide() {
            return this._toggleDOM(false);
        }
    }, {
        key: 'destroy',
        value: function destroy() {
            this._events.destroy();
            if (this.domElement) {
                this.settings.parent.removeChild(this.domElement);
            }
        }
    }, {
        key: '_bindEvents',
        value: function _bindEvents() {
            var _this2 = this;

            var that = this,
                dom = this.domElement,
                events = this._events;

            function addEvent(target, type, handler) {
                events.add(target, type, handler);
            }

            addEvent(dom, 'click', function (e) {
                return e.preventDefault();
            });

            dragTrack(events, this._domH, function (x, y) {
                return that._setHSLA(x);
            });

            dragTrack(events, this._domSL, function (x, y) {
                return that._setHSLA(null, x, 1 - y);
            });

            if (this.settings.alpha) {
                dragTrack(events, this._domA, function (x, y) {
                    return that._setHSLA(null, null, null, 1 - y);
                });
            }

            var editInput = this._domEdit;
            {
                addEvent(editInput, 'input', function (e) {
                    that._setColor(this.value, { fromEditor: true, failSilently: true });
                });

                addEvent(editInput, 'focus', function (e) {
                    var input = this;

                    if (input.selectionStart === input.selectionEnd) {
                        input.select();
                    }
                });
            }

            this._ifPopup(function () {

                var popupCloseProxy = function popupCloseProxy(e) {
                    return _this2.closeHandler(e);
                };

                addEvent(window, EVENT_CLICK_OUTSIDE, popupCloseProxy);
                addEvent(window, EVENT_TAB_MOVE, popupCloseProxy);
                onKey(events, dom, ['Esc', 'Escape'], popupCloseProxy);

                var timeKeeper = function timeKeeper(e) {
                    _this2.__containedEvent = e.timeStamp;
                };
                addEvent(dom, EVENT_CLICK_OUTSIDE, timeKeeper);

                addEvent(dom, EVENT_TAB_MOVE, timeKeeper);

                addEvent(_this2._domCancel, 'click', popupCloseProxy);
            });

            var onDoneProxy = function onDoneProxy(e) {
                _this2._ifPopup(function () {
                    return _this2.closeHandler(e);
                });
                if (_this2.onDone) {
                    _this2.onDone(_this2.colour);
                }
            };
            addEvent(this._domOkay, 'click', onDoneProxy);
            onKey(events, dom, ['Enter'], onDoneProxy);
        }
    }, {
        key: '_setPosition',
        value: function _setPosition() {
            var parent = this.settings.parent,
                elm = this.domElement;

            if (parent !== elm.parentNode) {
                parent.appendChild(elm);
            }

            this._ifPopup(function (popup) {

                if (getComputedStyle(parent).position === 'static') {
                    parent.style.position = 'relative';
                }

                var cssClass = popup === true ? 'popup_right' : 'popup_' + popup;

                ['popup_top', 'popup_bottom', 'popup_left', 'popup_right'].forEach(function (c) {

                    if (c === cssClass) {
                        elm.classList.add(c);
                    } else {
                        elm.classList.remove(c);
                    }
                });

                elm.classList.add(cssClass);
            });
        }
    }, {
        key: '_setHSLA',
        value: function _setHSLA(h, s, l, a, flags) {
            flags = flags || {};

            var col = this.colour,
                hsla = col.hsla;

            [h, s, l, a].forEach(function (x, i) {
                if (x || x === 0) {
                    hsla[i] = x;
                }
            });
            col.hsla = hsla;

            this._updateUI(flags);

            if (this.onChange && !flags.silent) {
                this.onChange(col);
            }
        }
    }, {
        key: '_updateUI',
        value: function _updateUI(flags) {
            if (!this.domElement) {
                return;
            }
            flags = flags || {};

            var col = this.colour,
                hsl = col.hsla,
                cssHue = 'hsl(' + hsl[0] * HUES + ', 100%, 50%)',
                cssHSL = col.hslString,
                cssHSLA = col.hslaString;

            var uiH = this._domH,
                uiSL = this._domSL,
                uiA = this._domA,
                thumbH = $('.picker_selector', uiH),
                thumbSL = $('.picker_selector', uiSL),
                thumbA = $('.picker_selector', uiA);

            function posX(parent, child, relX) {
                child.style.left = relX * 100 + '%';
            }
            function posY(parent, child, relY) {
                child.style.top = relY * 100 + '%';
            }

            posX(uiH, thumbH, hsl[0]);

            this._domSL.style.backgroundColor = this._domH.style.color = cssHue;

            posX(uiSL, thumbSL, hsl[1]);
            posY(uiSL, thumbSL, 1 - hsl[2]);

            uiSL.style.color = cssHSL;

            posY(uiA, thumbA, 1 - hsl[3]);

            var opaque = cssHSL,
                transp = opaque.replace('hsl', 'hsla').replace(')', ', 0)'),
                bg = 'linear-gradient(' + [opaque, transp] + ')';

            this._domA.style.backgroundImage = bg + ', ' + BG_TRANSP;

            if (!flags.fromEditor) {
                var format = this.settings.editorFormat,
                    alpha = this.settings.alpha;

                var value = void 0;
                switch (format) {
                    case 'rgb':
                        value = col.printRGB(alpha);break;
                    case 'hsl':
                        value = col.printHSL(alpha);break;
                    default:
                        value = col.printHex(alpha);
                }
                this._domEdit.value = value;
            }

            this._domSample.style.color = cssHSLA;
        }
    }, {
        key: '_ifPopup',
        value: function _ifPopup(actionIf, actionElse) {
            if (this.settings.parent && this.settings.popup) {
                actionIf && actionIf(this.settings.popup);
            } else {
                actionElse && actionElse();
            }
        }
    }, {
        key: '_toggleDOM',
        value: function _toggleDOM(toVisible) {
            var dom = this.domElement;
            if (!dom) {
                return false;
            }

            var displayStyle = toVisible ? '' : 'none',
                toggle = dom.style.display !== displayStyle;

            if (toggle) {
                dom.style.display = displayStyle;
            }
            return toggle;
        }
    }], [{
        key: 'StyleElement',
        get: function get$$1() {
            return _style;
        }
    }]);
    return Picker;
}();

/* harmony default export */ var vanilla_picker = (Picker);

;// CONCATENATED MODULE: ./src/helpers/hex_to_rgba.js
function hex_to_rgba(hex) {
  var c; // #ABC or #ABCD

  if (/^#([A-Fa-f0-9]{3,4})$/.test(hex)) {
    c = (hex + 'F').substring(1).split('');
    return hex_to_rgba(c[0], c[0], c[1], c[1], c[2], c[2], c[3], c[3]);
  }

  c = '0x' + (hex.substring(1) + 'FF').substring(0, 8);
  return 'rgba(' + [c >> 24 & 255, c >> 16 & 255, c >> 8 & 255, Math.round((c & 255) / 25.5) / 10].join(',') + ')';
}
;// CONCATENATED MODULE: ./src/helpers/decode_entities.js
/* harmony default export */ var decode_entities = ((function () {
  // this prevents any overhead from creating the object each time
  var element = document.createElement('div');

  function decodeHTMLEntities(str) {
    if (str && typeof str === 'string') {
      // strip script/html tags
      str = str.replace(/<script[^>]*>([\S\s]*?)<\/script>/gmi, '');
      str = str.replace(/<\/?\w(?:[^"'>]|"[^"]*"|'[^']*')*>/gmi, '');
      element.innerHTML = str;
      str = element.textContent;
      element.textContent = '';
    }

    return str;
  }

  return decodeHTMLEntities;
})());
;// CONCATENATED MODULE: ./src/admin.js
/*globals jQuery, wp, bsi_settings */



;

(function ($, s) {
  var editor = $('#' + s);

  if (editor.length < 1) {
    return false;
  }

  var $body = $('body'),
      imageeditor = editor.find('.area--background .background'),
      logoeditor = editor.find('.area--logo:not(.logo-alternate) .logo');

  $.fn.attachMediaUpload = function () {
    var wp_media_post_id = wp.media.model.settings.post.id; // Store the old id
    // Restore the main ID when the add media button is pressed

    $('a.add_media').on('click', function () {
      // console.log('is this really needed?');
      wp.media.model.settings.post.id = wp_media_post_id;
    });
    return $(this).each(function () {
      var file_frame,
          wrap = $(this),
          input = wrap.find('input').not('.button'),
          preview = wrap.find('.image-preview-wrapper img'),
          current_image_id = input.val(),
          button = wrap.find('.button').not('.remove'),
          remove = wrap.find('.button.remove');
      remove.on('click', function () {
        var attachment = {
          id: '0',
          url: 'data:image/png;base64,R0lGODlhAQABAIAAAP///wAAACH5BAEAAAAALAAAAAABAAEAAAICRAEAOw=='
        };
        preview.attr('src', attachment.url);
        input.trigger('image:select', [attachment]);
        input.val(attachment.id);
        current_image_id = attachment.id;
      }); // Uploading files

      button.on('click', function (event) {
        event.preventDefault(); // If the media frame already exists, reopen it.

        if (!file_frame) {
          // console.log('new file_frame');
          wp.media.model.settings.post.id = current_image_id; // Create the media frame.

          file_frame = wp.media.frames.file_frame = wp.media({
            title: 'Select an image or upload one.',
            button: {
              text: 'Use this image'
            },
            library: {
              type: wrap.data('types').split(',')
            },
            multiple: false // Set to true to allow multiple files to be selected

          }); // When an image is selected, run a callback.

          file_frame.on('select', function () {
            // We set multiple to false so only get one image from the uploader
            var attachment = file_frame.state().get('selection').first().toJSON();

            if ('sizes' in attachment && 'og-image' in attachment.sizes) {
              attachment.url = attachment.sizes['og-image'].url;
            } // Do something with attachment.id and/or attachment.url here


            preview.attr('src', attachment.url);
            input.trigger('image:select', [attachment]);
            input.val(attachment.id);
            current_image_id = attachment.id; // Restore the main post ID

            wp.media.model.settings.post.id = wp_media_post_id;
          }).on('open', function () {
            var selection = file_frame.state().get('selection');

            if (current_image_id) {
              selection.add(wp.media.attachment(current_image_id));
            }
          });
        } // Set the post ID to what we want
        // file_frame.uploader.options.uploader.params.post_id = current_image_id;
        // Open frame
        // console.log('opening file_frame');


        file_frame.open();
      });
    });
  };

  $.fn.attachFileUpload = function () {
    var wp_media_post_id = wp.media.model.settings.post.id; // Store the old id
    // Restore the main ID when the add media button is pressed

    $('a.add_media').on('click', function () {
      // console.log('is this really needed?');
      wp.media.model.settings.post.id = wp_media_post_id;
    });
    return $(this).each(function () {
      var file_frame,
          wrap = $(this),
          input = wrap.find('input').not('.button'),
          current_image_id = input.val(),
          button = wrap.find('.button').not('.remove'); // Uploading files

      button.on('click', function (event) {
        event.preventDefault(); // If the media frame already exists, reopen it.

        if (!file_frame) {
          // console.log('new file_frame');
          wp.media.model.settings.post.id = current_image_id; // Create the media frame.

          file_frame = wp.media.frames.file_frame = wp.media({
            title: 'Select a file or upload one',
            button: {
              text: 'Use this file'
            },
            library: {
              type: wrap.data('types').split(',')
            },
            multiple: false // Set to true to allow multiple files to be selected

          }); // console.log(file_frame);
          // When an image is selected, run a callback.

          file_frame.on('select', function () {
            // We set multiple to false so only get one image from the uploader
            var attachment = file_frame.state().get('selection').first().toJSON(); // Do something with attachment.id and/or attachment.url here

            input.trigger('file:select', [attachment]); // console.log(attachment);

            input.val(attachment.id);
            current_image_id = attachment.id; // Restore the main post ID

            wp.media.model.settings.post.id = wp_media_post_id;
          }).on('open', function () {
            var selection = file_frame.state().get('selection');

            if (current_image_id) {
              selection.add(wp.media.attachment(current_image_id));
            }
          });
        } // Set the post ID to what we want
        // file_frame.uploader.options.uploader.params.post_id = current_image_id;
        // Open frame
        // console.log('opening file_frame');


        file_frame.open();
      });
    });
  };

  $(document).ready(function () {
    // $('.editable[contenteditable]').keydown(function (e) {
    // 	// trap the return key being pressed
    // 	if (e.keyCode === 13) {
    // 		// insert 2 br tags (if only one br tag is inserted the cursor won't go to the next line)
    // 		document.execCommand('insertHTML', false, '\n');
    // 		// prevent the default behaviour of return key pressed
    // 		return false;
    // 	}
    // });
    var texteditor = editor.find('.editable');
    var texteditor_target = editor.find('textarea.editable-target');
    editor.find('h2 .toggle').on('click touchend', function () {
      $(this).closest('[class^="area"]').toggleClass('closed');
    }); // bugfix: compose.js is preventing things like undo.

    editor.on('keypress keyup keydown', function (e) {
      e.stopPropagation();
    }); // native JS solution for paste-fix

    texteditor.get(0).addEventListener('paste', function () {
      // console.log('PASTE with visual editor');
      setTimeout(function () {
        // strip all HTML
        var text = texteditor.text();
        var html = texteditor.html();

        if (text !== html) {
          text = text.replace('<br[^>]*>/g', '\n'); // console.log('cleaning from', html);
          // console.log('cleaning to', text);

          texteditor.text(text).trigger('keyup');
        }
      }, 250);
    }); // update target when editor edited

    texteditor.on('blur keyup paste', function () {
      // console.log('interaction with visual editor: ', e);
      setTimeout(function () {
        texteditor_target.val(texteditor.text());
      }, 250); // once we edit, remove the automatic update

      editor.removeClass('auto-title');
    });
    texteditor_target.on('blur keyup input', function () {
      // console.log('interaction with hidden field: ', e);
      texteditor_target.val(texteditor_target.val().replace(/&nbsp;<br/g, '<br'));
      texteditor_target.val(texteditor_target.val().replace(/&nbsp;\n/g, '\n'));
      texteditor.text(texteditor_target.val()); // once we edit, remove the automatic update

      editor.removeClass('auto-title');
    }); // update editor when target edited

    texteditor_target.on('blur keyup paste', function () {
      // console.log('copy hidden field to visual: ', e);
      texteditor.text(decode_entities(texteditor_target.val()));
    }).trigger('paste'); // text color

    editor.find('#color').on('keyup blur paste input', function () {
      editor.get(0).style.setProperty('--text-color', hex_to_rgba($(this).val()));
    }); // text background color

    editor.find('#background_enabled,#background_color').on('keyup blur paste input change', function () {
      var use_background = editor.find('#background_enabled').is(':checked');
      editor.toggleClass('with-text-background', use_background);
      editor.get(0).style.setProperty('--text-background', hex_to_rgba(editor.find('#background_color').val()));
    }).trigger('blur'); // text shadow options

    editor.find('#text_shadow_color').on('keyup blur paste input', function () {
      editor.get(0).style.setProperty('--text-shadow-color', hex_to_rgba($(this).val()));
    }).trigger('blur');
    editor.find('#text_shadow_top').on('keyup blur paste input', function () {
      editor.get(0).style.setProperty('--text-shadow-top', parseInt($(this).val(), 10) + 'px');
    }).trigger('blur');
    editor.find('#text_shadow_left').on('keyup blur paste input', function () {
      editor.get(0).style.setProperty('--text-shadow-left', parseInt($(this).val(), 10) + 'px');
    }).trigger('blur');
    editor.find('#text_shadow_enabled').on('change', function () {
      if ($(this).is(':checked')) {
        editor.get(0).style.setProperty('--text-shadow-color', hex_to_rgba('#555555DD'));
      } else {
        editor.get(0).style.setProperty('--text-shadow-color', hex_to_rgba('#00000000'));
      }
    }).trigger('change');
    editor.find('#disabled').on('change', function () {
      editor.toggleClass('bsi-disabled', $(this).is(':checked'));
    }); // positions

    $('.wrap-position-grid input').on('change', function () {
      var c = $(this).closest('.wrap-position-grid');
      var n = c.data('name');
      editor.removeClass(function (index, className) {
        return (className.match(new RegExp('(^|\\s)' + n + '-\\S+', 'g')) || []).join(' ');
      }).addClass(n + '-' + c.find('input:checked').attr('value'));
    }).trigger('change'); // logo size

    $('#image_logo_size').on('keyup blur paste input', function () {
      var v = parseInt('0' + $(this).val(), 10);
      var logo_min = parseInt($(this).attr('min'), 10);
      var logo_max = parseInt($(this).attr('max'), 10);

      if (v < logo_min) {
        v = logo_min;
      }

      if (v > logo_max) {
        v = logo_max;
      }

      editor.get(0).style.setProperty('--logo-scale', v);
    }).trigger('blur'); // font size

    $('#text__font_size').on('keyup blur paste input', function () {
      var v = parseInt('0' + $(this).val(), 10);
      var fs_min = parseInt($(this).attr('min'), 10);
      var fs_max = parseInt($(this).attr('max'), 10);

      if (v < fs_min) {
        v = fs_min;
      }

      if (v > fs_max) {
        v = fs_max;
      }

      editor.get(0).style.setProperty('--font-size', v + 'px');
      editor.get(0).style.setProperty('--line-height', v * 1.25 + 'px');
    }).trigger('blur'); // sliders

    $('.add-slider').each(function () {
      var $input = $(this).find('input');
      $input.attr('size', 4).on('blur change', function () {
        $(this).next('.a-slider').slider('value', parseInt($(this).val(), 10));
        $(this).trigger('input');
      }).after('<div class="a-slider"></div>');
      $input.next('.a-slider').slider({
        min: parseInt($input.attr('min'), 10),
        max: parseInt($input.attr('max'), 10),
        step: parseInt($input.attr('step'), 10),
        value: parseInt($input.attr('value'), 10),
        change: function change(event, ui) {
          $(this).prev('input').val(ui.value).trigger('input');
        },
        slide: function slide(event, ui) {
          $(this).prev('input').val(ui.value).trigger('input');
        }
      });
    });
    editor.find('#image').on('image:select', function (event, attachment) {
      imageeditor.get(0).style.backgroundImage = 'url("' + attachment.url + '")';
    });
    editor.find('#image_logo').on('image:select', function (event, attachment) {
      if ('id' in attachment && parseInt('' + attachment.id, 10) > 0) {
        editor.get(0).style.setProperty('--logo-width', attachment.width);
        editor.get(0).style.setProperty('--logo-height', attachment.height);
        logoeditor.get(0).style.backgroundImage = 'url("' + attachment.url + '")';
        editor.addClass('with-logo');
      } else {
        editor.get(0).style.setProperty('--logo-width', 410); // this is the example logo

        editor.get(0).style.setProperty('--logo-height', 82);
        logoeditor.get(0).style.backgroundImage = '';
        editor.removeClass('with-logo');
      }
    });
    editor.find('#text__ttf_upload').on('file:select', function (event, attachment) {
      $(this).parent().find('.filename').html(attachment.filename);
    });
    editor.find('i.toggle-comment,i.toggle-info').on('click touchend', function () {
      $(this).toggleClass('active');
    });
    editor.find('#text__font').on('keyup blur paste input change', function () {
      editor.get(0).style.setProperty('--text-font', $(this).val());
      editor.attr('data-font', $(this).val());
    }).trigger('blur'); // font face is defined in *admin.php

    editor.find('#text_enabled').on('change', function () {
      $('.area--text').toggleClass('invisible', !$(this).is(':checked'));
    }).trigger('change'); // font face is defined in *admin.php

    $('.input-color', editor).each(function () {
      var $input = $(this).find('input');
      new vanilla_picker({
        parent: this,
        popup: 'top',
        color: $input.val(),
        onChange: function onChange(color) {
          $input.val(color.hex.toUpperCase()).parent().get(0).style.setProperty('--the-color', hex_to_rgba(color.hex));
          $input.trigger('blur');
        } // onDone: function(color){},
        // onOpen: function(color){},
        // onClose: function(color){}

      });
    });

    var getFeaturedImage = function getFeaturedImage() {}; // window.getFeaturedImage = getFeaturedImage;


    var subscribe,
        state = {
      yoast: false,
      rankmath: false,
      featured: false
    };

    if ($body.is('.block-editor-page')) {
      var select = wp.data.select;
      subscribe = wp.data.subscribe;

      var _coreDataSelect, _coreEditorSelect;

      var getMediaById = function getMediaById(mediaId) {
        if (!_coreDataSelect) {
          _coreDataSelect = select('core');
        }

        return _coreDataSelect.getMedia(mediaId);
      };

      var getPostAttribute = function getPostAttribute(attribute) {
        if (!_coreEditorSelect) {
          _coreEditorSelect = select('core/editor');
        }

        return _coreEditorSelect.getEditedPostAttribute(attribute);
      };

      getFeaturedImage = function getFeaturedImage() {
        var featuredImage = getPostAttribute('featured_media');

        if (featuredImage) {
          var mediaObj = getMediaById(featuredImage);

          if (mediaObj) {
            if (bsi_settings.image_size_name in mediaObj.media_details.sizes) {
              return mediaObj.media_details.sizes[bsi_settings.image_size_name].source_url;
            }

            return mediaObj.source_url;
          }
        }

        return null;
      };
    } else {
      getFeaturedImage = function getFeaturedImage() {
        return $('#set-post-thumbnail img').attr('src') || '';
      };
    } // yoast?? no events on the input, use polling


    var getYoastFacebookImage = function getYoastFacebookImage() {
      var url;
      var $field = $('#facebook-url-input-metabox');
      var $preview = $('#wpseo-section-social > div:nth(0) .yoast-image-select__preview--image');

      if (!$field.length && !$preview.length) {
        return false;
      }

      url = $field.val();

      if (!url) {
        url = $preview.attr('src');
      }

      return url;
    }; // rankmath?? no events on the input, use polling


    var getRankMathFacebookImage = function getRankMathFacebookImage() {
      var url;
      var $preview = $('.rank-math-social-preview-facebook .rank-math-social-image-thumbnail');

      if (!$preview.length) {
        url = state.rankmath;
      } else if ($preview.attr('src').match(/wp-content\/plugins\/seo-by-rank-math\//)) {
        url = false;
      } else {
        url = $preview.attr('src');
      }

      return url;
    };

    state = {
      yoast: getYoastFacebookImage(),
      rankmath: getYoastFacebookImage(),
      featured: getFeaturedImage()
    }; // window.i_state = state;
    // window.i_gyfi = getYoastFacebookImage;
    // window.i_grmfi = getRankMathFacebookImage;
    // window.i_gfi = getFeaturedImage;

    var external_images_maybe_changed = function external_images_maybe_changed() {
      setTimeout(function () {
        var url; // yoast

        if ($('.area--background-alternate.image-source-yoast').length) {
          url = getYoastFacebookImage();

          if (state.yoast !== url) {
            state.yoast = url;

            if (url) {
              $('.area--background-alternate.image-source-yoast .background').get(0).style.backgroundImage = 'url("' + url + '")';
            } else {
              $('.area--background-alternate.image-source-yoast .background').get(0).style.backgroundImage = '';
            }
          }
        } // rankmath


        if ($('.area--background-alternate.image-source-rankmath').length) {
          url = getRankMathFacebookImage();

          if (state.rankmath !== url) {
            state.rankmath = url;

            if (url) {
              $('.area--background-alternate.image-source-rankmath .background').get(0).style.backgroundImage = 'url("' + url + '")';
            } else {
              $('.area--background-alternate.image-source-rankmath .background').get(0).style.backgroundImage = '';
            }
          }
        } // thumbnail


        if ($('.area--background-alternate.image-source-thumbnail').length) {
          url = getFeaturedImage();

          if (state.featured !== url) {
            state.featured = url;

            if (url) {
              $('.area--background-alternate.image-source-thumbnail .background').get(0).style.backgroundImage = 'url("' + url + '")';
            } else {
              $('.area--background-alternate.image-source-thumbnail .background').get(0).style.backgroundImage = '';
            }
          }
        }
      }, 500);
    };

    if ($body.is('.block-editor-page')) {
      var debounce;
      subscribe(function () {
        if (debounce) {
          clearTimeout(debounce);
        }

        debounce = setTimeout(function () {
          external_images_maybe_changed();
        }, 1000);
      });
    }

    setInterval(external_images_maybe_changed, 5000); // monitor available space for editor 
    // you might be wondering, why?
    // we use zoom to scale the entire interface because otherwise we would have to size all images and the text based on viewport width... which is even more crap

    var monitor_space = function monitor_space() {
      var w = $("#branded-social-images").outerWidth();

      if (w < 600) {
        editor.get(0).style.setProperty('--editor-scale', (w - 26) / 600 * .5);
      } else {
        editor.get(0).style.setProperty('--editor-scale', .5);
      }
    };

    $(window).on('resize', monitor_space);
    setTimeout(monitor_space, 1000); // monitor title

    var title_field = $('.wp-admin,.block-editor-page').filter('.post-new-php,.edit-php').find('#post #title,.block-editor #post-title-0').get(0);

    var update_auto_title = function update_auto_title() {
      // sure?
      if (editor.is('.auto-title')) {
        var new_title = bsi_settings.title_format.replace('{title}', $(title_field).val());
        texteditor_target.val(new_title);
        texteditor.text(new_title);
      } else {
        $(title_field).off(update_auto_title);
      }
    };

    if (title_field) {
      $(title_field).on('keyup change blur', update_auto_title).trigger('keyup');
    }
  });
})(jQuery, 'branded-social-images-editor');
/******/ })()
;