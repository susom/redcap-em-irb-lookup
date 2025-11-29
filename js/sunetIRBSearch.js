;
{
    // Define module namespace
    const module = ExternalModules.Stanford.IRB = ExternalModules.Stanford.IRB || {};

    Object.assign(module, {
        irbList: [],
        dropdown: null,
        inputEl: null,         // Sunet search input
        irbEl: null,           // NEW: protocol number search input
        lastResults: null,

        init() {
            //--------------------------------------
            // GET FIELD REFERENCES
            //--------------------------------------
            this.inputEl = document.querySelector(`input[name="${this.sunetField}"]`);
            this.irbEl   = document.querySelector(`input[name="${this.irbField}"]`);

            if (!this.inputEl) return;

            //--------------------------------------
            // CREATE SUNET SEARCH UI
            //--------------------------------------
            this.buildSearchUI({
                input: this.inputEl,
                buttonId: "irb-search-button",
                onSearch: (val) => this.fetchIRBList(val)
            });

            //--------------------------------------
            // CREATE IRB NUMBER SEARCH UI
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
                const clickedInput = this.inputEl === event.target;

                if (!clickedInsideDropdown && !clickedInput) {
                    this.removeDropdown();
                }
            });

            //--------------------------------------
            // REOPEN DROPDOWN
            //--------------------------------------
            this.inputEl.addEventListener("focus", () => {
                if (this.dropdown) return;
                if (this.lastResults && Array.isArray(this.lastResults)) {
                    this.renderDropdown(this.lastResults);
                }
            });
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
            return this.ajax("getIRBNumsBySunetID", { sunet })
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
            return this.ajax("getAllIrbInformation", { protocolNumber })
                .then(results => {
                    // --- Handle empty array response ---
                    if (Array.isArray(results) && results.length === 0) {
                        this.renderErrorDropdown("No Results found", this.irbEl);   // <-- dropdown anchored to the IRB input
                        return;
                    }


                    const attributeMap = ExternalModules.Stanford.IRB.attributeMap;
                    if (!attributeMap) return;

                    for (const key in attributeMap) {
                        if (results.hasOwnProperty(key)) {
                            const inputName = attributeMap[key];

                            const el = document.querySelector(
                                `input[name="${inputName}"], textarea[name="${inputName}"], select[name="${inputName}"]`
                            );

                            if (el) el.value = results[key];
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
            this.dropdown.style.width = this.inputEl.offsetWidth + "px";

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