/**
 * Academic Certificate Manager — visual layout designer (manager settings).
 */
(function($) {
	'use strict';

	var BLOCKS = ['header', 'body', 'footer', 'code'];
	var BLOCK_LABELS = {
		header: 'Header',
		body: 'Body',
		footer: 'Footer',
		code: 'Code'
	};

	function Designer($root) {
		this.$root = $root;
		this.type = $root.data('type');
		this.$canvas = $root.find('.acm-canvas');
		this.$bg = $root.find('.acm-bg-preview');
		this.$layoutInput = $('#layout' + capitalize(this.type));
		this.layout = parseLayout(this.$layoutInput.val());
		this.init();
	}

	Designer.prototype.init = function() {
		var self = this;
		this.bindFields();
		this.renderBlocks();
		this.updateAllBlockText();
		this.loadBackground();

		this.$root.find('.acm-bg-upload').on('change', function(e) {
			self.previewLocalFile(e.target.files[0]);
		});

		this.$root.find('.acm-use-default-bg').on('click', function(e) {
			e.preventDefault();
			var url = $('#acmDefaultBgUrl').val();
			if (url) {
				self.$bg.attr('src', url).show();
			}
		});

		$(window).on('resize.acmDesigner', debounce(function() {
			self.positionBlocksFromLayout();
		}, 150));
	};

	Designer.prototype.bindFields = function() {
		var self = this;
		var map = this.getFieldMap();

		$.each(map, function(block, selector) {
			$(document).on('input change', selector, function() {
				self.updateBlockText(block);
			});
		});

		$('#includeQRCode').on('change', function() {
			var visible = $(this).is(':checked');
			self.layout.code.visible = visible;
			self.$canvas.find('.acm-block-code').toggle(visible);
			self.saveLayout();
		});
	};

	Designer.prototype.getFieldMap = function() {
		var p = this.type;
		if (p === 'reviewer') {
			return {
				header: '[id^="headerText"]',
				body: '[id^="bodyTemplate"]',
				footer: '[id^="footerText"]'
			};
		}
		if (p === 'acceptance') {
			return {
				header: '[id^="acceptanceHeaderText"]',
				body: '[id^="acceptanceBodyTemplate"]',
				footer: '[id^="acceptanceFooterText"]'
			};
		}
		if (p === 'author') {
			return {
				header: '[id^="authorHeaderText"]',
				body: '[id^="authorBodyTemplate"]',
				footer: '[id^="authorFooterText"]'
			};
		}
		return {
			header: '[id^="editorHeaderText"]',
			body: '[id^="editorBodyTemplate"]',
			footer: '[id^="editorFooterText"]'
		};
	};

	Designer.prototype.renderBlocks = function() {
		var self = this;
		var $canvas = this.$canvas;
		$canvas.find('.acm-block').remove();

		var includeCode = $('#includeQRCode').is(':checked');
		if (this.layout.code) {
			this.layout.code.visible = includeCode;
		}

		$.each(BLOCKS, function(_, block) {
			if (block === 'code' && !includeCode) {
				return;
			}
			var $el = $('<div/>', {
				'class': 'acm-block acm-block-' + block,
				'data-block': block,
				'title': BLOCK_LABELS[block]
			});
			$el.append($('<span/>', {'class': 'acm-block-label', text: BLOCK_LABELS[block]}));
			$el.append($('<div/>', {'class': 'acm-block-content'}));
			$canvas.append($el);

			if ($.fn.draggable) {
				$el.draggable({
					containment: $canvas,
					cursor: 'move',
					stop: function() {
						self.layoutFromDom();
						self.saveLayout();
					}
				});
			}
		});

		this.positionBlocksFromLayout();
		this.applyDesignerStyles();
	};

	Designer.prototype.positionBlocksFromLayout = function() {
		var self = this;
		var cw = this.$canvas.innerWidth();
		var ch = this.$canvas.innerHeight();
		if (!cw || !ch) {
			return;
		}

		$.each(BLOCKS, function(_, block) {
			var cfg = self.layout[block];
			if (!cfg) {
				return;
			}
			var $el = self.$canvas.find('.acm-block-' + block);
			if (!$el.length) {
				return;
			}
			var w = (cfg.w / 100) * cw;
			var left = (cfg.x / 100) * cw;
			var top = (cfg.y / 100) * ch;
			$el.css({
				width: w + 'px',
				left: left + 'px',
				top: top + 'px',
				textAlign: alignToCss(cfg.align)
			});
		});
	};

	Designer.prototype.layoutFromDom = function() {
		var self = this;
		var cw = this.$canvas.innerWidth();
		var ch = this.$canvas.innerHeight();
		if (!cw || !ch) {
			return;
		}

		this.$canvas.find('.acm-block').each(function() {
			var block = $(this).data('block');
			var pos = $(this).position();
			var w = $(this).outerWidth();
			self.layout[block] = self.layout[block] || {};
			self.layout[block].x = clamp((pos.left / cw) * 100, 0, 95);
			self.layout[block].y = clamp((pos.top / ch) * 100, 0, 95);
			self.layout[block].w = clamp((w / cw) * 100, 10, 100);
		});
	};

	Designer.prototype.updateAllBlockText = function() {
		var self = this;
		$.each(BLOCKS, function(_, block) {
			if (block !== 'code') {
				self.updateBlockText(block);
			}
		});
		this.$canvas.find('.acm-block-code .acm-block-content').text('Certificate Code: PREVIEW12345');
	};

	Designer.prototype.updateBlockText = function(block) {
		if (block === 'code') {
			return;
		}
		var map = this.getFieldMap();
		var selector = map[block];
		if (!selector) {
			return;
		}
		var val = $(selector).not('[style*="display:none"]').filter('textarea, input').first().val() || '';
		val = val.replace(/\{\{\$[^}]+\}\}/g, '…');
		if (!val.trim()) {
			val = BLOCK_LABELS[block];
		}
		this.$canvas.find('.acm-block-' + block + ' .acm-block-content').text(val.substring(0, 200));
	};

	Designer.prototype.loadBackground = function() {
		var bgField = this.$root.data('bg-field');
		var url = $('#acmBgUrl_' + bgField).val();
		if (!url) {
			url = $('#acmDefaultBgUrl').val();
		}
		if (url) {
			this.$bg.attr('src', url).show();
		}
	};

	Designer.prototype.previewLocalFile = function(file) {
		if (!file || !file.type.match('image.*')) {
			return;
		}
		var reader = new FileReader();
		var self = this;
		reader.onload = function(e) {
			self.$bg.attr('src', e.target.result).show();
		};
		reader.readAsDataURL(file);
	};

	Designer.prototype.applyDesignerStyles = function() {
		var r = parseInt($('[id^="textColorR"]').val(), 10) || 0;
		var g = parseInt($('[id^="textColorG"]').val(), 10) || 0;
		var b = parseInt($('[id^="textColorB"]').val(), 10) || 0;
		var size = parseInt($('[id^="fontSize"]').val(), 10) || 12;
		var family = $('[id^="fontFamily"]').val() || 'dejavusans';
		var cssFamily = family === 'times' ? 'Times New Roman' : (family === 'courier' ? 'Courier New' : 'DejaVu Sans, Helvetica, Arial, sans-serif');

		this.$canvas.find('.acm-block-content').css({
			color: 'rgb(' + r + ',' + g + ',' + b + ')',
			fontFamily: cssFamily,
			fontSize: Math.max(8, Math.round(size * 0.85)) + 'px'
		});
		this.$canvas.find('.acm-block-header .acm-block-content').css('fontSize', Math.max(10, Math.round(size * 1.6)) + 'px');
	};

	Designer.prototype.saveLayout = function() {
		this.$layoutInput.val(JSON.stringify(this.layout));
	};

	function parseLayout(raw) {
		var defaults = {
			header: {x: 10, y: 10, w: 80, align: 'C', fontScale: 2.0},
			body: {x: 10, y: 32, w: 80, h: 40, align: 'C', fontScale: 1.167},
			footer: {x: 10, y: 76, w: 80, align: 'C', fontScale: 0.833},
			code: {x: 35, y: 88, w: 30, align: 'C', fontScale: 0.667, visible: true}
		};
		if (!raw) {
			return defaults;
		}
		try {
			var parsed = JSON.parse(raw);
			$.each(defaults, function(k, v) {
				parsed[k] = $.extend({}, v, parsed[k] || {});
			});
			return parsed;
		} catch (e) {
			return defaults;
		}
	}

	function capitalize(s) {
		return s.charAt(0).toUpperCase() + s.slice(1);
	}

	function clamp(n, min, max) {
		return Math.max(min, Math.min(max, n));
	}

	function alignToCss(align) {
		if (align === 'L') return 'left';
		if (align === 'R') return 'right';
		return 'center';
	}

	function debounce(fn, ms) {
		var t;
		return function() {
			clearTimeout(t);
			var args = arguments;
			var ctx = this;
			t = setTimeout(function() { fn.apply(ctx, args); }, ms);
		};
	}

	window.ACMCertificateDesigner = {
		init: function() {
			var designers = [];
			$('.acm-designer-root').each(function() {
				designers.push(new Designer($(this)));
			});

			$('.acm-cert-tab').on('click', function(e) {
				e.preventDefault();
				var tab = $(this).data('tab');
				$('.acm-cert-tab').removeClass('is-active');
				$(this).addClass('is-active');
				$('.acm-cert-panel').removeClass('is-active');
				$('#acm-panel-' + tab).addClass('is-active');
				$(window).trigger('resize.acmDesigner');
			});

			$('#certificateSettingsForm').on('submit', function() {
				$.each(designers, function(_, d) {
					d.layoutFromDom();
					d.saveLayout();
				});
			});

			$('[id^="fontSize"], [id^="fontFamily"], [id^="textColorR"], [id^="textColorG"], [id^="textColorB"]').on('input change', function() {
				$.each(designers, function(_, d) {
					d.applyDesignerStyles();
				});
			});
		}
	};

	$(function() {
		if ($('.acm-designer-root').length) {
			ACMCertificateDesigner.init();
		}
	});
})(jQuery);
