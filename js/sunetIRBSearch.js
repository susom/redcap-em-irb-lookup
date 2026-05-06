;
{
    // Define module namespace
    const module = ExternalModules.Stanford.IRB = ExternalModules.Stanford.IRB || {};

    /**
     * Detect if the current page is a REDCap survey (URL contains /surveys/).
     * @returns {boolean}
     */
    const isSurveyPage = () => /\/surveys\//.test(window.location.pathname);

    /**
     * On survey pages, resolve the authenticated user from the webauth_user field.
     * Returns the field value if on a survey page and the field exists, otherwise null.
     * @returns {string|null}
     */
    const getSurveyUserId = () => {
        if (!isSurveyPage()) return null;
        const el = document.querySelector('input[name="webauth_user"]');
        return el ? el.value.trim() || null : null;
    };

    Object.assign(module, {
        irbList: [],
        dropdown: null,
        inputEl: null,         // Sunet search input
        irbEl: null,           // NEW: protocol number search input
        lastResults: null,
        normalizeDdMonYy(input, validationType) {
            let val = (input || "").trim();
            if (!val) return val;

            // 1) Split into date + optional time (but ignore incoming time; we override)
            let datePart = val;
            const spaceIdx = val.indexOf(" ");
            if (spaceIdx !== -1) {
                datePart = val.slice(0, spaceIdx);
            }

            // 2) Decide default time based on validation type
            let timePart = "";
            if (/datetime/.test(validationType)) {
                if (/seconds/.test(validationType)) {
                    timePart = "00:00:00";
                } else {
                    timePart = "00:00";
                }
            }

            // 3) Detect delimiter in IRB date (e.g., 06-MAY-15 or 06/05/2015)
            const delim = datePart.includes("-")
                ? "-"
                : (datePart.includes("/") ? "/" : ".");

            const parts = datePart.split(delim).map(p => p.trim());
            if (parts.length !== 3) {
                return val; // Not expected format, return as-is
            }

            let [p1, p2, p3] = parts;

            // 4) Assume IRB sends DAY-MONTH-YEAR:
            //    - If any token has letters, that token is the month (e.g., 06-MAY-15)
            //    - Otherwise, treat as numeric D-M-Y (e.g., 06-05-15)
            let dayToken, monthToken, yearToken;

            if (/[A-Za-z]/.test(p1) || /[A-Za-z]/.test(p2) || /[A-Za-z]/.test(p3)) {
                // Month is the token with letters
                if (/[A-Za-z]/.test(p2)) {
                    dayToken   = p1;
                    monthToken = p2;
                    yearToken  = p3;
                } else if (/[A-Za-z]/.test(p1)) {
                    monthToken = p1;
                    dayToken   = p2;
                    yearToken  = p3;
                } else {
                    dayToken   = p1;
                    yearToken  = p2;
                    monthToken = p3;
                }
            } else {
                // Numeric only: treat as D-M-Y
                dayToken   = p1;
                monthToken = p2;
                yearToken  = p3;
            }

            if (!dayToken || !monthToken || !yearToken) {
                return val;
            }

            // 5) Normalize day
            const day = dayToken.padStart(2, "0");

            // 6) Normalize month (handle names like MAY or numeric)
            const months = {
                JAN: "01", FEB: "02", MAR: "03", APR: "04", MAY: "05", JUN: "06",
                JUL: "07", AUG: "08", SEP: "09", OCT: "10", NOV: "11", DEC: "12"
            };
            const upperMon = monthToken.toUpperCase();
            const month = (months[upperMon] || monthToken).padStart(2, "0");

            // 7) Normalize year: convert YY to YYYY using simple pivot
            let year = yearToken.trim();
            if (year.length === 2) {
                const yy = parseInt(year, 10);
                if (!Number.isNaN(yy)) {
                    year = ((yy >= 70 ? 1900 : 2000) + yy).toString();
                }
            }

            // Now we have canonical pieces as strings: year, month, day
            const pieces = { y: year, m: month, d: day };

            // 8) Determine desired order from validationType suffix (e.g., 'mdy', 'dmy')
            const underscoreIndex = validationType.indexOf("_");
            let order = "ymd";
            if (underscoreIndex !== -1) {
                order = validationType.slice(underscoreIndex + 1).toLowerCase(); // e.g., mdy, dmy
            }

            // 9) Build date string in requested order using '-' as delimiter
            const orderedParts = [];
            for (const ch of order) {
                if (pieces[ch]) {
                    orderedParts.push(pieces[ch]);
                }
            }

            // Fallback: if something went wrong, keep original value
            if (orderedParts.length !== 3) {
                return val;
            }

            const formattedDate = orderedParts.join("-");

            // 10) Append default time if applicable
            return timePart ? `${formattedDate} ${timePart}` : formattedDate;
        },

        convertYmdToFormat(ymd, format, delim) {
            const parts = (ymd || "").split("-");
            if (parts.length !== 3) return ymd;

            const [Y, M, D] = parts;

            switch ((format || "").toLowerCase()) {
                case "mdy":
                    return `${M}${delim}${D}${delim}${Y}`;
                case "dmy":
                    return `${D}${delim}${M}${delim}${Y}`;
                case "ymd":
                default:
                    return `${Y}${delim}${M}${delim}${D}`;
            }
        },

        init() {
            //--------------------------------------
            // GET FIELD REFERENCES
            // Each may be null if its feature is not enabled in EM settings
            //--------------------------------------
            this.inputEl = this.sunetField
                ? document.querySelector(`input[name="${this.sunetField}"]`)
                : null;
            this.irbEl = this.irbField
                ? document.querySelector(`input[name="${this.irbField}"]`)
                : null;

            // Nothing to do if neither field exists on this page
            if (!this.inputEl && !this.irbEl) return;

            //--------------------------------------
            // CREATE SUNET SEARCH UI (if enabled)
            //--------------------------------------
            if (this.inputEl) {
                this.buildSearchUI({
                    input: this.inputEl,
                    buttonId: "irb-search-button",
                    onSearch: (val) => this.fetchIRBList(val)
                });
            }

            //--------------------------------------
            // CREATE IRB NUMBER SEARCH UI (if enabled)
            //--------------------------------------
            if (this.irbEl) {
                this.buildSearchUI({
                    input: this.irbEl,
                    buttonId: "irb-direct-search-button",
                    onSearch: (val) => this.getAllIrbInformation(val)
                });
            }

            //--------------------------------------
            // CLOSE DROPDOWN WHEN CLICKING OUTSIDE
            //--------------------------------------
            document.addEventListener("click", (event) => {
                if (!this.dropdown) return;

                const clickedInsideDropdown = this.dropdown.contains(event.target);
                const clickedOnSearchInput  = event.target === this.inputEl
                                           || event.target === this.irbEl;

                if (!clickedInsideDropdown && !clickedOnSearchInput) {
                    this.removeDropdown();
                }
            });

            //--------------------------------------
            // REOPEN SUNET DROPDOWN ON FOCUS
            //--------------------------------------
            if (this.inputEl) {
                this.inputEl.addEventListener("focus", () => {
                    if (this.dropdown) return;
                    if (this.lastResults && Array.isArray(this.lastResults)) {
                        this.renderDropdown(this.lastResults);
                    }
                });

                // Clear cached results and dropdown when the input value changes
                this.inputEl.addEventListener("input", () => {
                    this.lastResults = null;
                    this.removeDropdown();
                });
            }
        },

        //--------------------------------------
        // REUSABLE SEARCH UI BUILDER
        //--------------------------------------
        buildSearchUI({ input, buttonId, onSearch }) {
            const wrapper = document.createElement("div");
            wrapper.style.position = "relative";
            wrapper.style.display = "inline-block";
            wrapper.style.width = input.offsetWidth + "px";

            input.parentNode.insertBefore(wrapper, input);
            wrapper.appendChild(input);

            // Search button
            const btn = document.createElement("button");
            btn.id = buttonId;
            btn.type = "button";
            btn.style.position = "absolute";
            btn.style.height = "100%";
            btn.style.cursor = "pointer";
            btn.style.borderTopRightRadius = "3px";
            btn.style.borderBottomRightRadius = "3px";
            btn.style.borderLeft = "none";

            const icon = document.createElement("i");
            icon.className = "fa-solid fa-magnifying-glass";
            btn.appendChild(icon);

            const recomputeButtonWidth = () => {
                const parentWidth = wrapper.offsetWidth;
                const inputWidth = input.offsetWidth;
                btn.style.width = (parentWidth - inputWidth) + "px";
            };

            recomputeButtonWidth();
            wrapper.appendChild(btn);

            //--------------------------------------
            // SEARCH BUTTON CLICK HANDLER
            //--------------------------------------
            btn.addEventListener("click", async () => {
                const val = input.value.trim();
                if (val.length === 0) return;

                btn.disabled = true;

                // Spinner
                while (btn.firstChild) btn.removeChild(btn.firstChild);
                const spinner = document.createElement("i");
                spinner.className = "fa-solid fa-spinner fa-spin";
                btn.appendChild(spinner);

                try {
                    await onSearch(val);
                } finally {
                    btn.disabled = false;

                    // Restore icon
                    while (btn.firstChild) btn.removeChild(btn.firstChild);
                    const i2 = document.createElement("i");
                    i2.className = "fa-solid fa-magnifying-glass";
                    btn.appendChild(i2);
                }
            });
        },

        //--------------------------------------
        // FETCH LIST OF IRBS BY SUNET
        //--------------------------------------
        fetchIRBList(sunet) {
            // On survey pages, pass webauth_user as the user identity for enforcement
            const payload = { sunet };
            const surveyUser = getSurveyUserId();
            if (surveyUser) payload.survey_user_id = surveyUser;

            return this.ajax("getIRBNumsBySunetID", payload)
                .then(results => {
                    if (!results?.success) {
                        this.lastResults = null;
                        this.renderErrorDropdown(results.error);
                        return;
                    }

                    if (!Array.isArray(results?.data)) return;

                    this.lastResults = results.data;
                    this.renderDropdown(results.data);
                });
        },

        //--------------------------------------
        // FETCH FULL IRB DETAILS
        //--------------------------------------
        getAllIrbInformation(protocolNumber) {
            // On survey pages, pass webauth_user as the user identity for enforcement
            const payload = { protocolNumber };
            const surveyUser = getSurveyUserId();
            if (surveyUser) payload.survey_user_id = surveyUser;

            return this.ajax("getAllIrbInformation", payload)
                .then(results => {
                    // --- Handle enforcement error (e.g., protocol not associated with current user) ---
                    if (results && results.success === false && results.error) {
                        this.renderErrorDropdown(results.error, this.irbEl);
                        return;
                    }

                    // --- Handle empty array response ---
                    if (Array.isArray(results) && results.length === 0) {
                        this.renderErrorDropdown("No Results found", this.irbEl);
                        return;
                    }


                    const attributeMap = ExternalModules.Stanford.IRB.attributeMap;
                    if (!attributeMap) return;

                    for (const key in attributeMap) {
                        if (results.hasOwnProperty(key)) {
                            const inputName = attributeMap[key]['field_name'];

                            const el = document.querySelector(
                                `input[name="${inputName}"], textarea[name="${inputName}"], select[name="${inputName}"]`
                            );

                            if (el) {
                                const elementType = attributeMap[key]['element_type'];
                                const validationType = attributeMap[key]['element_validation_type'] || '';
                                let value = results[key];

                                // --- YESNO FIELD (radio buttons) ---
                                if (elementType === "yesno") {
                                    // REDCap yesno radio values: 1 = Yes, 0 = No
                                    const yesValue = (value === 'Y') ? "1" : "0";

                                    const radios = document.querySelectorAll(
                                        `input[type="radio"][name="${inputName}___radio"]`
                                    );

                                    radios.forEach(r => {
                                        r.checked = (r.value === yesValue);
                                    });

                                } else {
                                // --- DATE / DATETIME FIELDS (D/M/Y variants) ---
                                const dateValidations = [
                                    'date_dmy', 'date_mdy', 'date_ymd',
                                    'datetime_dmy', 'datetime_mdy', 'datetime_ymd',
                                    'datetime_seconds_dmy', 'datetime_seconds_mdy', 'datetime_seconds_ymd'
                                ];

                                if (dateValidations.includes(validationType) && typeof value === 'string') {
                                    value = this.normalizeDdMonYy(value, validationType);
                                }
                                    el.value = value;
                                }
                            }
                        }
                    }
                });
        },

        //--------------------------------------
        // DROPDOWN: SUCCESS LIST
        //--------------------------------------
        renderDropdown(list, targetInput = this.inputEl) {
            this.removeDropdown();

            this.dropdown = document.createElement("ul");
            this.dropdown.className = "list-group";
            this.dropdown.style.position = "absolute";
            this.dropdown.style.zIndex = "2000";
            this.dropdown.style.width = targetInput.offsetWidth + "px";

            if (!list || list.length === 0) {
                const li = document.createElement("li");
                li.className = "list-group-item text-muted";
                li.textContent = "No results returned";
                li.style.pointerEvents = "none";
                this.dropdown.appendChild(li);
            } else {
                list.forEach(item => {
                    const li = document.createElement("li");
                    li.className = "list-group-item";

                    const num = item.protocolNumber || "N/A";
                    const title = item.protocolTitle || "Untitled";

                    const strong = document.createElement("strong");
                    strong.textContent = `#${num}`;
                    li.appendChild(strong);
                    li.appendChild(document.createTextNode(` — ${title}`));

                    li.style.cursor = "pointer";

                    li.addEventListener("mouseenter", () => li.style.backgroundColor = "#f0f0f0");
                    li.addEventListener("mouseleave", () => li.style.backgroundColor = "");

                    li.addEventListener("click", async () => {
                        if (num !== "N/A") {
                            this.removeDropdown();

                            const btn = document.getElementById("irb-search-button");
                            if (!btn) return;

                            btn.disabled = true;
                            while (btn.firstChild) btn.removeChild(btn.firstChild);
                            const spinner = document.createElement("i");
                            spinner.className = "fa-solid fa-spinner fa-spin";
                            btn.appendChild(spinner);

                            try {
                                await this.getAllIrbInformation(num);
                            } finally {
                                btn.disabled = false;

                                while (btn.firstChild) btn.removeChild(btn.firstChild);
                                const icon = document.createElement("i");
                                icon.className = "fa-solid fa-magnifying-glass";
                                btn.appendChild(icon);
                            }
                        }
                    });

                    this.dropdown.appendChild(li);
                });
            }

            targetInput.insertAdjacentElement("afterend", this.dropdown);
        },

        //--------------------------------------
        // ERROR DROPDOWN
        //--------------------------------------
        renderErrorDropdown(message, targetInput = this.inputEl) {
            this.removeDropdown();

            this.dropdown = document.createElement("ul");
            this.dropdown.className = "list-group";
            this.dropdown.style.position = "absolute";
            this.dropdown.style.zIndex = "2000";
            this.dropdown.style.width = targetInput.offsetWidth + "px";

            const li = document.createElement("li");
            li.className = "list-group-item list-group-item-danger";
            li.style.fontWeight = "bold";
            li.textContent = message;

            this.dropdown.appendChild(li);
            targetInput.insertAdjacentElement("afterend", this.dropdown);
        },

        removeDropdown() {
            if (this.dropdown) {
                this.dropdown.remove();
                this.dropdown = null;
            }
        },
    });

    document.addEventListener("DOMContentLoaded", function () {
        if (module && typeof module.init === "function") {
            module.init();
        }
    });
}