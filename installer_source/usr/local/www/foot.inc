<?php
/*
 * foot.inc
 *
 * part of pfSense (https://www.pfsense.org)
 * Copyright (c) 2004-2013 BSD Perimeter
 * Copyright (c) 2013-2016 Electric Sheep Fencing
 * Copyright (c) 2014-2026 Rubicon Communications, LLC (Netgate)
 * All rights reserved.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

/*
 * We put jquery in the footer; it is the preferred position for speed
 * and helps developers to use events[] instead of executing scripts directly.
 */
?>
	</div>
	<footer class="footer">
		<div class="footer-inner">
			<div class="footer-left">
				<span class="footer-brand">pfSense</span>
				<span class="footer-badge">Community Edition</span>
				<span class="footer-sep">&bull;</span>
				<span class="footer-text"><?=gettext('developed & maintained by')?> <a target="_blank" href="https://netgate.com" class="footer-link">Netgate</a></span>
			</div>
			<div class="footer-right">
				<span class="footer-copyright">&copy; ESF 2004 - <?=date('Y')?></span>
				<span class="footer-sep">&bull;</span>
				<a target="_blank" href="https://pfsense.org/license" class="footer-link"><?=gettext('View License')?></a>
				<a id="tpr" href="#" title="<?=gettext('Top of page')?>" class="footer-back-top"><i class="fa-solid fa-chevron-up"></i></a>
			</div>
		</div>
	</footer>

	<!-- This use of filemtime() is intended to fool the browser into reloading the file (not using the cached version) if the file is changed -->

	<script src="/vendor/jquery/jquery-3.7.1.min.js?v=<?=filemtime('/usr/local/www/vendor/jquery/jquery-3.7.1.min.js')?>"></script>
 	<script src="/vendor/jquery-ui/jquery-ui-1.13.2.min.js?v=<?=filemtime('/usr/local/www/vendor/jquery-ui/jquery-ui-1.13.2.min.js')?>"></script>
	<script src="/vendor/bootstrap/js/bootstrap.min.js?v=<?=filemtime('/usr/local/www/vendor/bootstrap/js/bootstrap.min.js')?>"></script>
	<script src="/js/pfSense.js?v=<?=filemtime('/usr/local/www/js/pfSense.js')?>"></script>
	<script src="/js/pfSenseHelpers.js?v=<?=filemtime('/usr/local/www/js/pfSenseHelpers.js')?>"></script>
	<script src="/js/polyfills.js?v=<?=filemtime('/usr/local/www/js/polyfills.js')?>"></script>
	<script src="/vendor/sortable/sortable.js?v=<?=filemtime('/usr/local/www/vendor/sortable/sortable.js')?>"></script>

	<script type="text/javascript" src="/vendor/jquery-treegrid/js/jquery.treegrid.min.js?v=<?=filemtime('/usr/local/www/vendor/jquery-treegrid/js/jquery.treegrid.min.js')?>"></script>
	<script type="text/javascript" src="/vendor/js-cookie/js.cookie.js?v=<?=filemtime('/usr/local/www/vendor/js-cookie/js.cookie.js')?>"></script>

	<script type="text/javascript">
	//<![CDATA[
		$(document).ready(function() {
			var isDesktop = function() { return $(window).width() >= 992; };

			// Restore collapsed sidebar state on desktop
			var isCollapsed = localStorage.getItem('pfsense_sidebar_collapsed') === 'true';
			if (isCollapsed && isDesktop()) {
				$('body').addClass('sidebar-collapsed');
			}

			// Sidebar toggle button (Hamburger)
			$('#sidebar-toggle-btn').on('click', function(e) {
				e.preventDefault();
				e.stopPropagation();
				if (isDesktop()) {
					$('body').toggleClass('sidebar-collapsed');
					localStorage.setItem('pfsense_sidebar_collapsed', $('body').hasClass('sidebar-collapsed'));
					// Hide any open flyouts on collapse toggle
					$('.sidebar-item').removeClass('open pinned').find('.sidebar-flyout').hide();
				} else {
					$('body').toggleClass('sidebar-mobile-open');
				}
			});

			// Mobile close button and backdrop
			$('#sidebar-close-btn, #sidebar-backdrop').on('click', function(e) {
				e.preventDefault();
				$('body').removeClass('sidebar-mobile-open');
			});

			// Desktop: Show & align MikroTik drop-right panel
			function showDropRight($item) {
				if (!isDesktop()) return;
				var $flyout = $item.children('.sidebar-flyout');
				if (!$flyout.length) return; // Menu tunggal (KVM / Terminal) tidak memiliki flyout
				$('.sidebar-item').not($item).removeClass('open').find('.sidebar-flyout').hide();
				$item.addClass('open');
				if ($flyout.length) {
					var itemRect = $item[0].getBoundingClientRect();
					var sidebarWidth = $('#pf-sidebar').outerWidth();
					var flyoutTop = itemRect.top;

					$flyout.css({
						'display': 'block',
						'position': 'fixed',
						'left': sidebarWidth + 'px',
						'top': flyoutTop + 'px',
						'z-index': '99999'
					});

					// Smart boundary detection: keep flyout above fixed footer and in viewport
					var flyoutHeight = $flyout.outerHeight();
					var footerHeight = $('.footer').outerHeight() || 38;
					var maxBottom = $(window).height() - footerHeight - 4;
					if (flyoutTop + flyoutHeight > maxBottom) {
						var adjustedTop = Math.max(52, maxBottom - flyoutHeight);
						$flyout.css('top', adjustedTop + 'px');
					}
				}
			}

			function hideDropRight($item) {
				if (!isDesktop()) return;
				if (!$item.hasClass('pinned')) {
					$item.removeClass('open');
					$item.children('.sidebar-flyout').hide();
				}
			}

			// Desktop: Hover handling
			$('.sidebar-item').on('mouseenter', function() {
				if (isDesktop()) {
					showDropRight($(this));
				}
			}).on('mouseleave', function() {
				if (isDesktop()) {
					hideDropRight($(this));
				}
			});

			// Click handler: Mobile accordion toggle OR Desktop MikroTik click-to-pin
			$('.sidebar-link').on('click', function(e) {
				if ($(this).hasClass('direct-link')) {
					if (!isDesktop()) {
						$('body').removeClass('sidebar-mobile-open');
					}
					return; // Menu tunggal langsung navigasi ke URL tujuan
				}
				e.preventDefault();
				var $item = $(this).closest('.sidebar-item');

				if (isDesktop()) {
					var isPinned = $item.hasClass('pinned');
					$('.sidebar-item').removeClass('pinned open').find('.sidebar-flyout').hide();
					if (!isPinned) {
						$item.addClass('pinned');
						showDropRight($item);
					}
				} else {
					var wasOpen = $item.hasClass('open');
					$('.sidebar-item').removeClass('open').find('.sidebar-flyout').hide();
					if (!wasOpen) {
						$item.addClass('open');
						$item.find('.sidebar-flyout').show();
					}
				}
			});

			// Close pinned drop-right when clicking outside on desktop
			$(document).on('click', function(e) {
				if (isDesktop() && !$(e.target).closest('#pf-sidebar, .sidebar-flyout').length) {
					$('.sidebar-item').removeClass('pinned open').find('.sidebar-flyout').hide();
				}
			});

			// Close mobile drawer when tapping any submenu link
			$('.sidebar-submenu a').on('click', function() {
				if (!isDesktop()) {
					$('body').removeClass('sidebar-mobile-open');
				}
			});

			// Highlight active link and parent category
			var currentScript = window.location.pathname.split('/').pop().split('?')[0];
			if (!currentScript || currentScript === '' || currentScript === 'index.php') {
				// Pada index/home, semua menu sidebar tidak boleh aktif sebelum diklik/dipilih
				$('.sidebar-item').removeClass('active');
				$('.sidebar-submenu li').removeClass('active');
				return;
			}

			// Pastikan help tidak pernah di-highlight sebagai menu halaman aktif
			$('#sub-help').closest('.sidebar-item').removeClass('active');

			if (currentScript === 'diag_terminal.php') {
				$('.sidebar-item').removeClass('active');
				$('.sidebar-link.direct-link[href="/diag_terminal.php"]').closest('.sidebar-item').addClass('active');
				return;
			}

			if (currentScript === 'services_virtual.php') {
				$('.sidebar-item').removeClass('active');
				$('.sidebar-link.direct-link[href="/services_virtual.php"]').closest('.sidebar-item').addClass('active');
				return;
			}

			$('.sidebar-submenu a').each(function() {
				var href = $(this).attr('href');
				if (href && !href.startsWith('http://') && !href.startsWith('https://')) {
					var hrefScript = href.split('?')[0].split('/').pop();
					if (hrefScript && hrefScript === currentScript) {
						$(this).parent().addClass('active');
						var $parentItem = $(this).closest('.sidebar-item');
						$parentItem.addClass('active');
						if (!isDesktop()) {
							$parentItem.addClass('open');
							$parentItem.find('.sidebar-flyout').show();
						}
					}
				}
			});

			$('.sidebar-link.direct-link').each(function() {
				var href = $(this).attr('href');
				if (href) {
					var hrefScript = href.split('?')[0].split('/').pop();
					if (hrefScript && hrefScript === currentScript) {
						$(this).closest('.sidebar-item').addClass('active');
					}
				}
			});
		});

		// Smooth scroll to top
		$(document).on('click', '#tpr, .footer-back-top', function(e) {
			e.preventDefault();
			$('html, body').animate({ scrollTop: 0 }, 200);
		});

		// Un-hide the "Top of page" icons if the page is larger than the window
		if ($(document).height() > $(window).height()) {
		    $('[id^=tp]').show();
		}
	//]]>
	</script>
</body>
</html>
