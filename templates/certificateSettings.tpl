{**
 * plugins/generic/academicCertificate/templates/certificateSettings.tpl
 *
 * Copyright (c) 2024
 * Distributed under the GNU GPL v3.
 *
 * Certificate settings form template
 *}
<link rel="stylesheet" href="{$pluginUrl|escape}/css/certificateDesigner.css" />
<script src="{$pluginUrl|escape}/js/certificateDesigner.js"></script>
<input type="hidden" id="acmDefaultBgUrl" value="{$defaultBackgroundPreviewUrl|escape}" />
<script>
	$(function() {ldelim}
		// Flag to track if file is selected
		var fileSelected = false;

		// Initially setup as AJAX form
		$('#certificateSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');

		// Handle any background image file selection
		$('.certificate-background-upload').on('change', function(e) {ldelim}
			var file = e.target.files[0];
			var $input = $(this);
			if (file) {ldelim}
				if (!file.type.match('image.*')) {ldelim}
					alert('Please select an image file (JPEG or PNG)');
					$input.val('');
					return;
				{rdelim}
				if (file.size > 5 * 1024 * 1024) {ldelim}
					alert('Image file size must be less than 5MB');
					$input.val('');
					return;
				{rdelim}
				var previewId = 'preview_' + $input.attr('id');
				if ($('#' + previewId).length === 0) {ldelim}
					$input.after('<p id="' + previewId + '" class="description" style="color: #28a745; margin-top: 5px;"></p>');
				{rdelim}
				$('#' + previewId).text('Selected: ' + file.name);
				fileSelected = true;
			{rdelim}
		{rdelim});

		// Legacy single-field handler (kept for compatibility)
		$('#backgroundImage').on('change', function(e) {ldelim}
			// handled by .certificate-background-upload above
		{rdelim});

		// Intercept form submission BEFORE AjaxFormHandler
		$('#certificateSettingsForm').on('submit.fileUpload', function(e) {ldelim}
			if (fileSelected) {ldelim}
				console.log('AcademicCertificate: File selected - destroying AjaxFormHandler for regular submission');
				// Stop event propagation to prevent AjaxFormHandler from handling it
				e.stopImmediatePropagation();

				// Unbind AjaxFormHandler
				$(this).off('.pkpHandler');

				// Submit form normally
				console.log('AcademicCertificate: Submitting form with regular POST');
				this.submit();

				// Prevent default to avoid double submission
				return false;
			{rdelim}
			// For non-file submissions, let AJAX handler process it
		{rdelim});

		// Initialize color picker from RGB values
		function rgbToHex(r, g, b) {ldelim}
			return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
		{rdelim}

		function hexToRgb(hex) {ldelim}
			var result = /^#?([a-f\d]{ldelim}2{rdelim})([a-f\d]{ldelim}2{rdelim})([a-f\d]{ldelim}2{rdelim})$/i.exec(hex);
			return result ? {ldelim}
				r: parseInt(result[1], 16),
				g: parseInt(result[2], 16),
				b: parseInt(result[3], 16)
			{rdelim} : null;
		{rdelim}

		// Use attribute selectors to handle OJS's dynamic ID suffixes
		var $rInput = $('input[id^="textColorR"]');
		var $gInput = $('input[id^="textColorG"]');
		var $bInput = $('input[id^="textColorB"]');

		// Set initial color picker value from RGB inputs
		var r = parseInt($rInput.val()) || 0;
		var g = parseInt($gInput.val()) || 0;
		var b = parseInt($bInput.val()) || 0;
		$('#colorPicker').val(rgbToHex(r, g, b));
		$('#colorPreview').css('background-color', rgbToHex(r, g, b));

		// Update RGB values when color picker changes
		// Use 'change' and 'blur' events to ensure values are set before form serialization
		$('#colorPicker').on('input change blur', function() {ldelim}
			var rgb = hexToRgb($(this).val());
			if (rgb) {ldelim}
				$rInput.val(rgb.r);
				$gInput.val(rgb.g);
				$bInput.val(rgb.b);
				$('#colorPreview').css('background-color', $(this).val());
			{rdelim}
		{rdelim});

		// Update color picker when RGB values change manually
		$rInput.add($gInput).add($bInput).on('input change blur', function() {ldelim}
			// Get values and constrain to 0-255 range
			var r = Math.max(0, Math.min(255, parseInt($rInput.val()) || 0));
			var g = Math.max(0, Math.min(255, parseInt($gInput.val()) || 0));
			var b = Math.max(0, Math.min(255, parseInt($bInput.val()) || 0));

			// Update the input values if they were out of range
			$rInput.val(r);
			$gInput.val(g);
			$bInput.val(b);

			// Update color picker and preview
			$('#colorPicker').val(rgbToHex(r, g, b));
			$('#colorPreview').css('background-color', rgbToHex(r, g, b));
		{rdelim});
	{rdelim});
</script>

<form class="pkp_form" id="certificateSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="settings" save=true}" enctype="multipart/form-data">
	{csrf}
	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="certificateSettingsFormNotification"}

	<div id="description" class="acm-settings-intro">
		<p>{translate key="plugins.generic.academicCertificate.settings.description"}</p>
		<p class="description">{translate key="plugins.generic.academicCertificate.settings.landscapeA4Hint"}</p>
	</div>

	{fbvFormArea id="certificateDefaultBackground" title="plugins.generic.academicCertificate.settings.backgroundImages"}
		<div class="acm-default-bg-row">
			{fbvFormSection title="plugins.generic.academicCertificate.settings.backgroundImageDefault" description="plugins.generic.academicCertificate.settings.backgroundImageDefaultDescription"}
				<input type="file" id="backgroundImage" name="backgroundImage" accept="image/jpeg,image/png" class="pkp_form_file certificate-background-upload" />
				{if $backgroundImages.backgroundImage}
					<p class="description">{translate key="plugins.generic.academicCertificate.settings.currentImage"}: {$backgroundImages.backgroundImage.name|escape}</p>
				{/if}
			{/fbvFormSection}
		</div>
	{/fbvFormArea}

	{fbvFormArea id="certificateDesigner" title="plugins.generic.academicCertificate.settings.designerTitle"}

		<nav class="acm-cert-tabs" role="tablist">
			{foreach from=$certificateTypesForDesigner key=typeId item=typeCfg name=certTabs}
				<button type="button" class="acm-cert-tab{if $smarty.foreach.certTabs.first} is-active{/if}" data-tab="{$typeId|escape}" role="tab">
					{$typeCfg.label|escape}
				</button>
			{/foreach}
		</nav>

		{foreach from=$certificateTypesForDesigner key=typeId item=typeCfg name=certPanels}
			<div class="acm-cert-panel{if $smarty.foreach.certPanels.first} is-active{/if}" id="acm-panel-{$typeId|escape}" role="tabpanel">

				<p class="acm-designer-hint">{translate key="plugins.generic.academicCertificate.settings.designerHint"}</p>

				<div class="acm-designer-layout acm-designer-root" data-type="{$typeId|escape}" data-bg-field="{$typeCfg.bgField|escape}">

					<div class="acm-canvas-wrap">
						<div class="acm-canvas">
							<img class="acm-bg-preview" alt="" />
						</div>
						<p class="acm-dimensions">
							{translate key="plugins.generic.academicCertificate.settings.designerDimensions" width=$a4LandscapeWidthMm height=$a4LandscapeHeightMm widthPx=$a4LandscapeWidthPx heightPx=$a4LandscapeHeightPx}
						</p>
					</div>

					<div class="acm-fields-panel">
						<input type="hidden" name="layout{$typeId|capitalize}" id="layout{$typeId|capitalize}" value="{$typeCfg.layoutJson|escape}" />
						{if $backgroundPreviewUrls[$typeCfg.bgField]}
							<input type="hidden" id="acmBgUrl_{$typeCfg.bgField|escape}" value="{$backgroundPreviewUrls[$typeCfg.bgField]|escape}" />
						{/if}

						{fbvFormSection title=$typeCfg.bgLabel description="plugins.generic.academicCertificate.settings.typeBackgroundImageDescription"}
							<input type="file" id="{$typeCfg.bgField|escape}" name="{$typeCfg.bgField|escape}" accept="image/jpeg,image/png" class="pkp_form_file certificate-background-upload acm-bg-upload" />
							{if $backgroundImages[$typeCfg.bgField]}
								<p class="description">{translate key="plugins.generic.academicCertificate.settings.currentImage"}: {$backgroundImages[$typeCfg.bgField].name|escape}</p>
							{/if}
							<p><a href="#" class="acm-use-default-bg">{translate key="plugins.generic.academicCertificate.settings.useDefaultBackground"}</a></p>
						{/fbvFormSection}

						{if $typeId == 'reviewer'}
							{assign var=hdrKey value="plugins.generic.academicCertificate.settings.headerText"}
							{assign var=hdrDesc value="plugins.generic.academicCertificate.settings.headerTextDescription"}
							{assign var=bodyKey value="plugins.generic.academicCertificate.settings.bodyTemplate"}
							{assign var=bodyDesc value="plugins.generic.academicCertificate.settings.bodyTemplateDescription"}
							{assign var=footKey value="plugins.generic.academicCertificate.settings.footerText"}
							{assign var=footDesc value="plugins.generic.academicCertificate.settings.footerTextDescription"}
							{assign var=varsKey value="plugins.generic.academicCertificate.settings.availableVariables"}
							{assign var=varsDesc value="plugins.generic.academicCertificate.settings.availableVariablesDescription"}
							{assign var=hdrId value="headerText"}
							{assign var=bodyId value="bodyTemplate"}
							{assign var=footId value="footerText"}
							{assign var=hdrRequired value=true}
							{assign var=bodyRequired value=true}
						{elseif $typeId == 'acceptance'}
							{assign var=hdrKey value="plugins.generic.academicCertificate.settings.acceptanceHeaderText"}
							{assign var=hdrDesc value="plugins.generic.academicCertificate.settings.acceptanceHeaderTextDescription"}
							{assign var=bodyKey value="plugins.generic.academicCertificate.settings.acceptanceBodyTemplate"}
							{assign var=bodyDesc value="plugins.generic.academicCertificate.settings.acceptanceBodyTemplateDescription"}
							{assign var=footKey value="plugins.generic.academicCertificate.settings.acceptanceFooterText"}
							{assign var=footDesc value="plugins.generic.academicCertificate.settings.acceptanceFooterTextDescription"}
							{assign var=varsKey value="plugins.generic.academicCertificate.settings.acceptanceAvailableVariables"}
							{assign var=varsDesc value="plugins.generic.academicCertificate.settings.acceptanceAvailableVariablesDescription"}
							{assign var=hdrId value="acceptanceHeaderText"}
							{assign var=bodyId value="acceptanceBodyTemplate"}
							{assign var=footId value="acceptanceFooterText"}
							{assign var=hdrRequired value=false}
							{assign var=bodyRequired value=false}
						{elseif $typeId == 'author'}
							{assign var=hdrKey value="plugins.generic.academicCertificate.settings.authorHeaderText"}
							{assign var=hdrDesc value="plugins.generic.academicCertificate.settings.authorHeaderTextDescription"}
							{assign var=bodyKey value="plugins.generic.academicCertificate.settings.authorBodyTemplate"}
							{assign var=bodyDesc value="plugins.generic.academicCertificate.settings.authorBodyTemplateDescription"}
							{assign var=footKey value="plugins.generic.academicCertificate.settings.authorFooterText"}
							{assign var=footDesc value="plugins.generic.academicCertificate.settings.authorFooterTextDescription"}
							{assign var=varsKey value="plugins.generic.academicCertificate.settings.authorAvailableVariables"}
							{assign var=varsDesc value="plugins.generic.academicCertificate.settings.authorAvailableVariablesDescription"}
							{assign var=hdrId value="authorHeaderText"}
							{assign var=bodyId value="authorBodyTemplate"}
							{assign var=footId value="authorFooterText"}
							{assign var=hdrRequired value=false}
							{assign var=bodyRequired value=false}
						{else}
							{assign var=hdrKey value="plugins.generic.academicCertificate.settings.editorHeaderText"}
							{assign var=hdrDesc value="plugins.generic.academicCertificate.settings.editorHeaderTextDescription"}
							{assign var=bodyKey value="plugins.generic.academicCertificate.settings.editorBodyTemplate"}
							{assign var=bodyDesc value="plugins.generic.academicCertificate.settings.editorBodyTemplateDescription"}
							{assign var=footKey value="plugins.generic.academicCertificate.settings.editorFooterText"}
							{assign var=footDesc value="plugins.generic.academicCertificate.settings.editorFooterTextDescription"}
							{assign var=varsKey value="plugins.generic.academicCertificate.settings.availableVariables"}
							{assign var=varsDesc value="plugins.generic.academicCertificate.settings.availableVariablesDescription"}
							{assign var=hdrId value="editorHeaderText"}
							{assign var=bodyId value="editorBodyTemplate"}
							{assign var=footId value="editorFooterText"}
							{assign var=hdrRequired value=false}
							{assign var=bodyRequired value=false}
						{/if}

						{fbvFormSection title=$hdrKey description=$hdrDesc required=$hdrRequired}
							{fbvElement type="text" id=$hdrId value=$typeCfg.headerValue maxlength="255" size=$fbvStyles.size.LARGE}
						{/fbvFormSection}

						{fbvFormSection title=$bodyKey description=$bodyDesc required=$bodyRequired}
							{fbvElement type="textarea" id=$bodyId value=$typeCfg.bodyValue height=$fbvStyles.height.TALL rich=false}
							<p class="description">
								{translate key="plugins.generic.academicCertificate.settings.defaultTemplate"}:
								<a href="#" onclick="$('[id^={$bodyId|escape}]').not('#defaultBodyTemplate_{$typeId|escape}').val($('#defaultBodyTemplate_{$typeId|escape}').val()); return false;">
									{translate key="plugins.generic.academicCertificate.settings.useDefaultTemplate"}
								</a>
							</p>
							<textarea id="defaultBodyTemplate_{$typeId|escape}" style="display:none;">{$typeCfg.defaultBody|escape}</textarea>
						{/fbvFormSection}

						{fbvFormSection title=$footKey description=$footDesc}
							{fbvElement type="textarea" id=$footId value=$typeCfg.footerValue height=$fbvStyles.height.SHORT}
						{/fbvFormSection}

						{fbvFormSection title=$varsKey}
							<div class="pkp_helpers_clear">
								<p class="description">{translate key=$varsDesc}</p>
								<ul class="template-variables">
									{foreach from=$typeCfg.variables item=variable}
										<li><code>{$variable}</code></li>
									{/foreach}
								</ul>
							</div>
						{/fbvFormSection}
					</div>
				</div>
			</div>
		{/foreach}

	{/fbvFormArea}

	{fbvFormArea id="certificateAppearance" class="acm-global-section" title="plugins.generic.academicCertificate.settings.appearance"}

		{* Font Family *}
		{fbvFormSection title="plugins.generic.academicCertificate.settings.fontFamily"}
			{fbvElement type="select" id="fontFamily" from=$fontOptions selected=$fontFamily translate=false defaultValue="helvetica"}
			<p class="description" style="color: #996600; margin-top: 5px;">
				{translate key="plugins.generic.academicCertificate.settings.fontUnicodeWarning"}
			</p>
		{/fbvFormSection}

		{* Font Size *}
		{fbvFormSection title="plugins.generic.academicCertificate.settings.fontSize"}
			{fbvElement type="text" id="fontSize" value=$fontSize|default:12 size=$fbvStyles.size.SMALL}
		{/fbvFormSection}

		{* Page Orientation *}
		{fbvFormSection title="plugins.generic.academicCertificate.settings.pageOrientation" description="plugins.generic.academicCertificate.settings.pageOrientationDescription"}
			{fbvElement type="select" id="pageOrientation" from=$orientationOptions selected=$pageOrientation translate=false defaultValue="L"}
		{/fbvFormSection}

		{* Text Color *}
		{fbvFormSection title="plugins.generic.academicCertificate.settings.textColor" description="plugins.generic.academicCertificate.settings.textColorDescription"}
			<div class="pkp_helpers_clear">
				<div style="margin-bottom: 10px;">
					<label for="colorPicker">Color Picker:</label>
					<input type="color" id="colorPicker" style="width: 60px; height: 30px; border: 1px solid #ccc; cursor: pointer;" />
					<span id="colorPreview" style="display: inline-block; width: 30px; height: 30px; border: 1px solid #ccc; margin-left: 5px; vertical-align: middle;"></span>
				</div>
				<div style="float:left; margin-right: 10px;">
					<label for="textColorR">R:</label>
					{fbvElement type="text" id="textColorR" value=$textColorR|default:0 size=$fbvStyles.size.SMALL inline=true}
				</div>
				<div style="float:left; margin-right: 10px;">
					<label for="textColorG">G:</label>
					{fbvElement type="text" id="textColorG" value=$textColorG|default:0 size=$fbvStyles.size.SMALL inline=true}
				</div>
				<div style="float:left;">
					<label for="textColorB">B:</label>
					{fbvElement type="text" id="textColorB" value=$textColorB|default:0 size=$fbvStyles.size.SMALL inline=true}
				</div>
			</div>
		{/fbvFormSection}

	{/fbvFormArea}

	{fbvFormArea id="certificateTypes" title="plugins.generic.academicCertificate.settings.certificateTypes"}

		{fbvFormSection list=true}
			{fbvElement type="checkbox" id="enableReviewerCertificates" value="1" checked=$enableReviewerCertificates|default:true label="plugins.generic.academicCertificate.settings.enableReviewerCertificates"}
			{fbvElement type="checkbox" id="enableAcceptanceCertificates" value="1" checked=$enableAcceptanceCertificates|default:true label="plugins.generic.academicCertificate.settings.enableAcceptanceCertificates"}
			{fbvElement type="checkbox" id="enableAuthorCertificates" value="1" checked=$enableAuthorCertificates|default:true label="plugins.generic.academicCertificate.settings.enableAuthorCertificates"}
			{fbvElement type="checkbox" id="enableEditorCertificates" value="1" checked=$enableEditorCertificates|default:true label="plugins.generic.academicCertificate.settings.enableEditorCertificates"}
			{fbvElement type="checkbox" id="hideReviewerSubmissionTitle" value="1" checked=$hideReviewerSubmissionTitle|default:true label="plugins.generic.academicCertificate.settings.hideReviewerSubmissionTitle"}
		{/fbvFormSection}

	{/fbvFormArea}

	{fbvFormArea id="certificateEligibility" title="plugins.generic.academicCertificate.settings.eligibility"}

		{* Certificate Number Prefix *}
		{fbvFormSection title="plugins.generic.academicCertificate.settings.certificateNumberPrefix" description="plugins.generic.academicCertificate.settings.certificateNumberPrefixDescription"}
			{fbvElement type="text" id="certificateNumberPrefix" value=$certificateNumberPrefix|default:'ACM' size=$fbvStyles.size.SMALL}
		{/fbvFormSection}

		{* Minimum Reviews *}
		{fbvFormSection title="plugins.generic.academicCertificate.settings.minimumReviews" description="plugins.generic.academicCertificate.settings.minimumReviewsDescription" required=true}
			{fbvElement type="text" id="minimumReviews" value=$minimumReviews|default:1 size=$fbvStyles.size.SMALL}
		{/fbvFormSection}

		{* Include QR Code *}
		{fbvFormSection title="plugins.generic.academicCertificate.settings.verification" list=true}
			{fbvElement type="checkbox" id="includeQRCode" value="1" checked=$includeQRCode label="plugins.generic.academicCertificate.settings.includeQRCode"}
		{/fbvFormSection}

	{/fbvFormArea}

	{fbvFormButtons}
	<p>
		<a href="{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="preview"}" target="_blank" class="pkp_button">
			{translate key="plugins.generic.academicCertificate.settings.previewCertificate"}
		</a>
	</p>
</form>

<!-- Certificate Statistics -->
<div class="section" style="margin-top: 40px;">
	<h2>{translate key="plugins.generic.academicCertificate.statistics.title"}</h2>
	<p class="description">{translate key="plugins.generic.academicCertificate.statistics.description"}</p>

	<div class="statistics-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 20px 0;">
		<div class="stat-card" style="background: #f5f5f5; padding: 20px; border-radius: 5px; text-align: center;">
			<h3 style="font-size: 2em; margin: 0; color: #007bff;">{$totalCertificates|default:0}</h3>
			<p style="margin: 5px 0 0 0; color: #666;">{translate key="plugins.generic.academicCertificate.statistics.totalCertificates"}</p>
		</div>
		<div class="stat-card" style="background: #f5f5f5; padding: 20px; border-radius: 5px; text-align: center;">
			<h3 style="font-size: 2em; margin: 0; color: #28a745;">{$totalDownloads|default:0}</h3>
			<p style="margin: 5px 0 0 0; color: #666;">{translate key="plugins.generic.academicCertificate.statistics.totalDownloads"}</p>
		</div>
		<div class="stat-card" style="background: #f5f5f5; padding: 20px; border-radius: 5px; text-align: center;">
			<h3 style="font-size: 2em; margin: 0; color: #ffc107;">{$uniqueReviewers|default:0}</h3>
			<p style="margin: 5px 0 0 0; color: #666;">{translate key="plugins.generic.academicCertificate.statistics.uniqueReviewers"}</p>
		</div>
	</div>
</div>

<!-- Batch Certificate Generation -->
<div class="section" style="margin-top: 40px; padding: 20px; background: #f9f9f9; border-radius: 5px;">
	<h2>{translate key="plugins.generic.academicCertificate.batch.title"}</h2>
	<p class="description">{translate key="plugins.generic.academicCertificate.batch.description"}</p>

	<form id="batchGenerateForm" style="margin-top: 20px;">
		<div style="margin-bottom: 15px;">
			<label>{translate key="plugins.generic.academicCertificate.batch.selectReviewers"}</label>
			<select id="batchReviewers" name="reviewerIds[]" multiple size="10" style="width: 100%; padding: 10px;">
				{if $eligibleReviewers}
					{foreach from=$eligibleReviewers item=reviewer}
						<option value="{$reviewer.id}">{$reviewer.name} ({$reviewer.completedReviews} {translate key="plugins.generic.academicCertificate.batch.completedReviews"})</option>
					{/foreach}
				{else}
					<option disabled>{translate key="plugins.generic.academicCertificate.batch.noEligibleReviewers"}</option>
				{/if}
			</select>
			<p class="description" style="margin-top: 5px;">{translate key="plugins.generic.academicCertificate.batch.selectMultipleHint"}</p>
		</div>

		<button type="button" id="generateBatchBtn" class="pkp_button" style="background: #28a745; color: white;">
			{translate key="plugins.generic.academicCertificate.batch.generate"}
		</button>
		<span id="batchProgress" style="margin-left: 15px; display: none;">
			<span class="pkp_spinner"></span> {translate key="plugins.generic.academicCertificate.batch.generating"}
		</span>
	</form>

	<div id="batchResult" style="margin-top: 20px; display: none;"></div>
</div>

<!-- Manual Acceptance Certificate Issuance -->
<div class="section" style="margin-top: 40px; padding: 20px; background: #f0f7ff; border-radius: 5px;">
	<h2>{translate key="plugins.generic.academicCertificate.issueAcceptance.title"}</h2>
	<p class="description">{translate key="plugins.generic.academicCertificate.issueAcceptance.description"}</p>

	<div style="margin-top: 15px;">
		<label for="issueAcceptanceSubmissionId">{translate key="plugins.generic.academicCertificate.issueAcceptance.submissionId"}</label>
		<input type="number" id="issueAcceptanceSubmissionId" min="1" style="width: 200px; margin-left: 10px; padding: 5px;" />
		<button type="button" id="issueAcceptanceBtn" class="pkp_button" style="margin-left: 10px;">
			{translate key="plugins.generic.academicCertificate.issueAcceptance.issue"}
		</button>
		<span id="issueAcceptanceProgress" style="margin-left: 15px; display: none;">
			<span class="pkp_spinner"></span> {translate key="plugins.generic.academicCertificate.issueAcceptance.issuing"}
		</span>
	</div>

	<div id="issueAcceptanceResult" style="margin-top: 15px; display: none;"></div>
</div>

<script>
$(document).ready(function() {ldelim}
	// Debug: Log that script is loaded
	if (console && console.log) {ldelim}
		console.log('AcademicCertificate: Batch generation script loaded');
	{rdelim}

	$('#generateBatchBtn').on('click', function() {ldelim}
		if (console && console.log) {ldelim}
			console.log('AcademicCertificate: Generate batch button clicked');
		{rdelim}

		var selectedReviewers = $('#batchReviewers').val();
		if (!selectedReviewers || selectedReviewers.length === 0) {ldelim}
			alert('{translate key="plugins.generic.academicCertificate.batch.noSelection" escape="js"}');
			return;
		{rdelim}

		// Show progress
		$('#batchProgress').show();
		$('#generateBatchBtn').prop('disabled', true);

		// Get CSRF token from the main form
		var csrfToken = $('#certificateSettingsForm input[name="csrfToken"]').val();

		// Build the URL
		var ajaxUrl = '{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="generateBatch" escape=false}';

		// Debug logging
		if (console && console.log) {ldelim}
			console.log('AcademicCertificate: Batch certificate generation started');
			console.log('AcademicCertificate: Selected reviewers:', selectedReviewers);
			console.log('AcademicCertificate: CSRF token present:', !!csrfToken);
			console.log('AcademicCertificate: AJAX URL:', ajaxUrl);
		{rdelim}

		$.ajax({ldelim}
			url: ajaxUrl,
			type: 'POST',
			data: {ldelim}
				reviewerIds: selectedReviewers,
				csrfToken: csrfToken
			{rdelim},
			success: function(response) {ldelim}
				$('#batchProgress').hide();
				$('#generateBatchBtn').prop('disabled', false);

				// Debug logging
				if (console && console.log) {ldelim}
					console.log('Batch generation response:', response);
				{rdelim}

				try {ldelim}
					var data = typeof response === 'string' ? JSON.parse(response) : response;
					if (data.status) {ldelim}
						var generatedCount = data.content && data.content.generated ? data.content.generated : 0;

						// Show success message
						if (generatedCount > 0) {ldelim}
							$('#batchResult').html(
								'<div style="padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; color: #155724; margin-bottom: 15px;">' +
								'<strong>✓ Success!</strong> Generated ' + generatedCount + ' certificate(s). Reviewers can now download their certificates.' +
								'</div>'
							).show();
						{rdelim} else {ldelim}
							$('#batchResult').html(
								'<div style="padding: 15px; background: #fff3cd; border: 1px solid #ffeeba; border-radius: 5px; color: #856404; margin-bottom: 15px;">' +
								'<strong>⚠ No certificates generated.</strong> Selected reviewers either already have certificates or don\'t have completed reviews.' +
								'</div>'
							).show();
						{rdelim}

						// Clear the selection
						$('#batchReviewers').val(null).trigger('change');

						// Don't reload page - let user continue working
						// Statistics will update on next settings open
					{rdelim} else {ldelim}
						$('#batchResult').html(
							'<div style="padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24;">' +
							'<strong>✗ Error:</strong> ' + (data.content || 'Failed to generate certificates') +
							'</div>'
						).show();
					{rdelim}
				{rdelim} catch (e) {ldelim}
					if (console && console.error) {ldelim}
						console.error('Failed to parse response:', e);
					{rdelim}
					$('#batchResult').html(
						'<div style="padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24;">' +
						'<strong>Error:</strong> Invalid response format' +
						'</div>'
					).show();
				{rdelim}
			{rdelim},
			error: function(xhr, status, error) {ldelim}
				$('#batchProgress').hide();
				$('#generateBatchBtn').prop('disabled', false);

				// Debug logging
				if (console && console.error) {ldelim}
					console.error('Batch generation failed:', status, error);
					console.error('Response:', xhr.responseText);
				{rdelim}

				$('#batchResult').html(
					'<div style="padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24;">' +
					'<strong>Error:</strong> {translate key="plugins.generic.academicCertificate.batch.error" escape="js"}' +
					'</div>'
				).show();
			{rdelim}
		{rdelim});
	{rdelim});

	$('#issueAcceptanceBtn').on('click', function() {ldelim}
		var submissionId = parseInt($('#issueAcceptanceSubmissionId').val(), 10);
		if (!submissionId || submissionId < 1) {ldelim}
			alert('{translate key="plugins.generic.academicCertificate.error.invalidSubmissionId" escape="js"}');
			return;
		{rdelim}

		var csrfToken = $('#certificateSettingsForm input[name="csrfToken"]').val();
		var ajaxUrl = '{url router=$smarty.const.ROUTE_COMPONENT op="manage" category="generic" plugin=$pluginName verb="issueAcceptance" escape=false}';

		$('#issueAcceptanceProgress').show();
		$('#issueAcceptanceBtn').prop('disabled', true);

		$.ajax({ldelim}
			url: ajaxUrl,
			type: 'POST',
			data: {ldelim}
				submissionId: submissionId,
				csrfToken: csrfToken
			{rdelim},
			success: function(response) {ldelim}
				$('#issueAcceptanceProgress').hide();
				$('#issueAcceptanceBtn').prop('disabled', false);

				try {ldelim}
					var data = typeof response === 'string' ? JSON.parse(response) : response;
					if (data.status) {ldelim}
						var message = (data.content && data.content.message) ? data.content.message : '{translate key="plugins.generic.academicCertificate.issueAcceptance.success" escape="js"}';
						$('#issueAcceptanceResult').html(
							'<div style="padding: 15px; background: #d4edda; border: 1px solid #c3e6cb; border-radius: 5px; color: #155724;">' +
							'<strong>✓</strong> ' + message +
							'</div>'
						).show();
					{rdelim} else {ldelim}
						$('#issueAcceptanceResult').html(
							'<div style="padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24;">' +
							'<strong>✗</strong> ' + (data.content || 'Failed') +
							'</div>'
						).show();
					{rdelim}
				{rdelim} catch (e) {ldelim}
					$('#issueAcceptanceResult').html(
						'<div style="padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24;">' +
						'<strong>Error:</strong> Invalid response format' +
						'</div>'
					).show();
				{rdelim}
			{rdelim},
			error: function() {ldelim}
				$('#issueAcceptanceProgress').hide();
				$('#issueAcceptanceBtn').prop('disabled', false);
				$('#issueAcceptanceResult').html(
					'<div style="padding: 15px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 5px; color: #721c24;">' +
					'<strong>Error:</strong> {translate key="plugins.generic.academicCertificate.issueAcceptance.error" escape="js"}' +
					'</div>'
				).show();
			{rdelim}
		{rdelim});
	{rdelim});
{rdelim});
</script>

<style>
.template-variables {
	display: grid;
	grid-template-columns: repeat(3, 1fr);
	gap: 5px;
	list-style: none;
	padding: 10px;
	background: #f5f5f5;
	border-radius: 3px;
}

.template-variables li {
	font-size: 0.9em;
}

.template-variables code {
	background: #fff;
	padding: 2px 5px;
	border-radius: 2px;
	font-family: monospace;
}
</style>
