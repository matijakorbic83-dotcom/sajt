(() => {
  const i18n = window.smilecraftI18n;
  if (!i18n) {
    console.error("The FAQ assistant requires the site localization module.");
    return;
  }

  const waitForLocalization = async () => {
    await i18n.ready;

    const t = (key) => i18n.t(`chatbot.${key}`);
    const element = (tag, className, text) => {
      const node = document.createElement(tag);
      if (className) node.className = className;
      if (text) node.textContent = text;
      return node;
    };

    const widget = element("div", "site-chatbot");
    const toggle = element("button", "site-chatbot__toggle");
    toggle.type = "button";
    toggle.setAttribute("aria-expanded", "false");
    toggle.setAttribute("aria-controls", "site-chatbot-panel");

    const toggleIcon = element("span", "site-chatbot__toggle-icon");
    toggleIcon.setAttribute("aria-hidden", "true");
    toggleIcon.innerHTML = '<svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 5.75h14a2 2 0 0 1 2 2v8.5a2 2 0 0 1-2 2h-8l-5 3v-3h-1a2 2 0 0 1-2-2v-8.5a2 2 0 0 1 2-2Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><path d="M8 11h8m-8 3h5" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/></svg>';
    const toggleLabel = element("span", "site-chatbot__toggle-label");
    toggle.append(toggleIcon, toggleLabel);

    const panel = element("section", "site-chatbot__panel");
    panel.id = "site-chatbot-panel";
    panel.hidden = true;
    panel.setAttribute("role", "region");

    const header = element("header", "site-chatbot__header");
    const headingGroup = element("div", "site-chatbot__heading-group");
    const heading = element("h2", "site-chatbot__title");
    heading.id = "site-chatbot-title";
    const subtitle = element("p", "site-chatbot__subtitle");
    headingGroup.append(heading, subtitle);

    const closeButton = element("button", "site-chatbot__close", "×");
    closeButton.type = "button";
    closeButton.setAttribute("aria-label", t("close"));
    closeButton.setAttribute("title", t("close"));
    closeButton.setAttribute("data-chatbot-close", "");
    closeButton.setAttribute("aria-controls", panel.id);
    header.append(headingGroup, closeButton);
    panel.setAttribute("aria-labelledby", heading.id);

    const body = element("div", "site-chatbot__body");
    const quickSection = element("section", "site-chatbot__quick");
    const quickHeading = element("h3", "site-chatbot__quick-heading");
    const quickButtons = element("div", "site-chatbot__quick-buttons");
    const quickQuestions = [
      ["appointmentQuestion", "appointment"],
      ["servicesQuestion", "services"],
      ["confirmationQuestion", "confirmation"],
      ["demoQuestion", "demo"],
    ];

    quickQuestions.forEach(([questionKey, intent]) => {
      const button = element("button", "site-chatbot__quick-button", t(questionKey));
      button.type = "button";
      button.addEventListener("click", () => ask(t(questionKey), intent));
      quickButtons.appendChild(button);
    });
    quickSection.append(quickHeading, quickButtons);

    const log = element("div", "site-chatbot__messages");
    log.id = "site-chatbot-messages";
    log.setAttribute("role", "log");
    log.setAttribute("aria-live", "polite");
    log.setAttribute("aria-relevant", "additions");

    const form = element("form", "site-chatbot__form");
    const inputLabel = element("label", "site-chatbot__sr-only");
    inputLabel.htmlFor = "site-chatbot-input";
    const input = element("input", "site-chatbot__input");
    input.id = "site-chatbot-input";
    input.name = "question";
    input.type = "text";
    input.maxLength = 240;
    input.autocomplete = "off";
    input.required = true;
    const sendButton = element("button", "site-chatbot__send", t("send"));
    sendButton.type = "submit";
    form.append(inputLabel, input, sendButton);

    body.append(quickSection, log);
    panel.append(header, body, form);
    widget.append(toggle, panel);
    document.body.appendChild(widget);

    const linkTargets = {
      appointment: ["booking.html", "bookingLink"],
      services: ["services.html", "servicesLink"],
      contact: ["contact.html", "contactLink"],
    };

    function addMessage(text, sender, linkIntent) {
      const message = element("article", `site-chatbot__message site-chatbot__message--${sender}`);
      const author = element(
        "span",
        "site-chatbot__message-author",
        t(sender === "user" ? "userLabel" : "assistantLabel")
      );
      const content = element("p", "site-chatbot__message-text", text);
      message.append(author, content);

      const target = linkTargets[linkIntent];
      if (target) {
        const link = element("a", "site-chatbot__message-link", t(target[1]));
        link.href = target[0];
        message.appendChild(link);
      }

      log.appendChild(message);
      log.scrollTop = log.scrollHeight;
    }

    function answerFor(intent) {
      const replies = {
        appointment: ["appointmentAnswer", "appointment"],
        services: ["servicesAnswer", "services"],
        confirmation: ["confirmationAnswer"],
        demo: ["demoAnswer"],
        medical: ["medicalAnswer"],
        contact: ["contactAnswer", "contact"],
        fallback: ["fallbackAnswer"],
      };
      const reply = replies[intent] || replies.fallback;
      addMessage(t(reply[0]), "assistant", reply[1]);
    }

    function normalize(value) {
      return value
        .toLocaleLowerCase()
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .trim();
    }

    function detectIntent(question) {
      const text = normalize(question);
      if (/potvrd|confirm|подтверж|подтверд|bestat|фиксац/.test(text)) return "confirmation";
      if (/zakaz|appointment|book|termin|закаж|запис|прием|приём|брони|vereinbar|buchen/.test(text)) return "appointment";
      if (/uslug|service|услуг|dienstleistung|behandlung|leistungen/.test(text)) return "services";
      if (/dijagno|diagnos|bol|pain|toothache|schmerz|лекарств|боль|зуб/.test(text)) return "medical";
      if (/stvarn|real|demo|izmis|fiction|wirklich|exist|echt|стварн|реаль|измиш|настоящ|reell/.test(text)) return "demo";
      if (/kontakt|contact|контакт|adresa|address|location|radno|hours|opening hours|offnungszeiten|oeffnungszeiten|cost|цена|price|kosten|стоим|график|часы|телефон|адрес/.test(text)) return "contact";
      return "fallback";
    }

    function ask(question, intent) {
      addMessage(question, "user");
      answerFor(intent || detectIntent(question));
    }

    function updateLabels() {
      const toggleKey = panel.hidden ? "open" : "close";
      toggleLabel.textContent = t(toggleKey);
      toggle.setAttribute("aria-label", t(toggleKey));
      closeButton.setAttribute("aria-label", t("close"));
      closeButton.setAttribute("title", t("close"));
      heading.textContent = t("title");
      subtitle.textContent = t("subtitle");
      quickHeading.textContent = t("quickHeading");
      quickButtons.querySelectorAll("button").forEach((button, index) => {
        button.textContent = t(quickQuestions[index][0]);
      });
      inputLabel.textContent = t("inputLabel");
      input.placeholder = t("placeholder");
      sendButton.textContent = t("send");
      log.setAttribute("aria-label", t("conversationLabel"));
      log.replaceChildren();
      addMessage(t("welcome"), "assistant");
    }

    function setOpen(open, restoreFocus = false) {
      panel.hidden = !open;
      toggle.setAttribute("aria-expanded", String(open));
      toggle.setAttribute("aria-label", t(open ? "close" : "open"));
      toggleLabel.textContent = t(open ? "close" : "open");
      if (open) input.focus();
      else if (restoreFocus) toggle.focus();
    }

    toggle.addEventListener("click", () => {
      setOpen(panel.hidden);
    });
    closeButton.addEventListener("click", () => setOpen(false, true));
    form.addEventListener("submit", (event) => {
      event.preventDefault();
      const question = input.value.trim();
      if (!question) return;
      input.value = "";
      ask(question);
      input.focus();
    });
    document.addEventListener("keydown", (event) => {
      if (event.key === "Escape" && !panel.hidden) setOpen(false, true);
    });
    window.addEventListener("smilecraft:localechange", updateLabels);

    updateLabels();
  };

  waitForLocalization().catch((error) => {
    console.error("Unable to initialize the FAQ assistant.", error);
  });
})();
