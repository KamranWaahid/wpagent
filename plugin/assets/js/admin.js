(function () {
	const strings = window.wpagentAdmin || {};

	const holder = document.getElementById("wpagent-disabled-fields");
	if (holder) {
		document.querySelectorAll(".wpagent-toggle-command").forEach(function (box) {
			box.addEventListener("change", function () {
				const name = box.getAttribute("data-command");
				holder.querySelectorAll('input[value="' + name + '"]').forEach(function (el) {
					el.remove();
				});
				if (!box.checked && name) {
					const input = document.createElement("input");
					input.type = "hidden";
					input.name = "disabled_commands[]";
					input.value = name;
					holder.appendChild(input);
				}
			});
		});
	}

	document.querySelectorAll("[data-wpagent-generate-form]").forEach(function (form) {
		form.addEventListener("submit", function () {
			const btn = form.querySelector("[data-wpagent-generate]");
			if (!btn) {
				return;
			}
			btn.disabled = true;
			btn.textContent = strings.generating || "Generating…";
		});
	});

	document.querySelectorAll("[data-wpagent-copy]").forEach(function (btn) {
		btn.addEventListener("click", function () {
			const id = btn.getAttribute("data-wpagent-copy");
			const field = id ? document.getElementById(id) : null;
			if (!field) {
				return;
			}
			const text = "value" in field ? field.value : field.textContent;
			const done = function (ok) {
				const prev = btn.getAttribute("data-wpagent-label") || btn.textContent;
				if (!btn.getAttribute("data-wpagent-label")) {
					btn.setAttribute("data-wpagent-label", prev);
				}
				btn.textContent = ok ? strings.copied || "Copied" : strings.copyFailed || "Copy failed";
				window.setTimeout(function () {
					btn.textContent = btn.getAttribute("data-wpagent-label") || prev;
				}, 1500);
			};
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(text).then(
					function () {
						done(true);
					},
					function () {
						fallbackCopy(field, text, done);
					}
				);
				return;
			}
			fallbackCopy(field, text, done);
		});
	});

	function fallbackCopy(field, text, done) {
		try {
			if (field && typeof field.select === "function") {
				field.focus();
				field.select();
			}
			const ok = document.execCommand("copy");
			done(ok);
		} catch (e) {
			done(false);
		}
	}

	document.querySelectorAll(".wpagent-remove-option").forEach(function (btn) {
		btn.addEventListener("click", function () {
			const row = btn.closest("tr");
			if (row) {
				row.remove();
			}
		});
	});
})();
