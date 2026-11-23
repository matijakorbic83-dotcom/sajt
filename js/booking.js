document.addEventListener('DOMContentLoaded', async () => {
  const i18n = window.smilecraftI18n;
  if (!i18n) return;
  await i18n.ready;

  const form = document.getElementById('booking-form');
  if (!form) {
    return;
  }

  const serviceSelect = document.getElementById('service_id');
  const clinicSelect = document.getElementById('clinic_id');
  const servicePicker = document.getElementById('service-picker');
  const servicePickerTrigger = document.getElementById('service-picker-trigger');
  const servicePickerPanel = document.getElementById('service-picker-panel');
  const servicePickerOptions = document.getElementById('service-picker-options');
  const serviceSearch = document.getElementById('booking-service-search');
  const selectedServiceName = document.getElementById('service-selected-name');
  const selectedServiceCategory = document.getElementById('service-selected-category');
  const selectedServiceDuration = document.getElementById('service-selected-duration');
  const dateInput = document.getElementById('appointment_date');
  const timeInput = document.getElementById('appointment_time');
  const timePicker = document.getElementById('time-slot-picker');
  const calendarGrid = document.getElementById('booking-calendar-grid');
  const calendarMonthLabel = document.getElementById('calendar-month');
  const calendarStatus = document.getElementById('calendar-status');
  const previousMonthButton = document.getElementById('calendar-previous');
  const nextMonthButton = document.getElementById('calendar-next');
  const submitButton = document.getElementById('booking-submit');
  const bookingLayout = form.querySelector('[data-booking-layout]');
  const bookingPanels = [...form.querySelectorAll('[data-booking-panel]')];
  const nextStepButtons = [...form.querySelectorAll('[data-booking-next]')];
  const backStepButtons = [...form.querySelectorAll('[data-booking-back]')];
  const statusText = document.getElementById('booking-status');
  const successPanel = document.getElementById('booking-success');
  const errorPanel = document.getElementById('booking-error');
  const clinicContainer = document.getElementById('clinic-field');

  const summaryService = document.querySelector('[data-summary-service]');
  const summaryClinic = document.querySelector('[data-summary-clinic]');
  const summaryDuration = document.querySelector('[data-summary-duration]');
  const summaryDate = document.querySelector('[data-summary-date]');
  const summaryTime = document.querySelector('[data-summary-time]');
  const serviceDuration = document.getElementById('service-duration');
  const bookingSteps = [...document.querySelectorAll('[data-booking-step]')];

  const state = {
    currentStep: 1,
    services: [],
    clinics: [],
    servicesLoading: true,
    clinicsLoading: true,
    servicesLoadFailed: false,
    clinicsLoadFailed: false,
    submitting: false,
    selectedTime: '',
    calendarMonth: null,
    dateAvailability: new Map(),
    calendarRequest: 0,
    calendarLoading: false,
    slotRequest: 0,
    allServices: [],
    lastSuccess: null,
    lastErrorCode: null,
    statusMessage: null,
    calendarMessage: null,
    timeEmptyMessageKey: null,
    fieldErrorKeys: new Map(),
  };

  const t = (key, params) => i18n.t(key, params);
  const successMessageKeys = {
    BOOKING_RECEIVED: 'booking.success.received',
  };
  const cyrillicToLatin = {
    а: 'a', б: 'b', в: 'v', г: 'g', д: 'd', ђ: 'đ', е: 'e', ж: 'ž', з: 'z',
    и: 'i', ј: 'j', к: 'k', л: 'l', љ: 'lj', м: 'm', н: 'n', њ: 'nj', о: 'o',
    п: 'p', р: 'r', с: 's', т: 't', ћ: 'ć', у: 'u', ф: 'f', х: 'h', ц: 'c',
    ч: 'č', џ: 'dž', ш: 'š',
  };
  const serviceNameKeys = [
    { key: 'generalDentistry', patterns: [/^(?:opsta stomatologija|opsti stomatoloski pregled|stomatoloski pregled)$/, /^general dentistry$/] },
    { key: 'teethWhitening', patterns: [/^(?:izbeljivanje|beljenje)(?: zuba)?$/, /^teeth whitening$/, /^whitening$/] },
    { key: 'implantConsultation', patterns: [/^(?:pregled|konsultacija za) (?:zubnih )?(?:im+plant(?:a|ata|ova)?|imlant(?:a|ata)?)$/, /^implant(?:at)? consultation$/] },
    { key: 'dentalImplants', patterns: [/^(?:(?:zubni )?im+plant(?:i|a|ata|ati|at|ovi)?|ugradnja im+planta)$/, /^dental implants?$/, /^implant placement$/] },
    { key: 'toothExtraction', patterns: [/^(?:vadjenje|vadenje) zuba$/, /^tooth extraction$/] },
    { key: 'dentalCrowns', patterns: [/^(?:zubne )?krunice$/, /^dental crowns?$/] },
    { key: 'orthodontics', patterns: [/^(?:ortodoncija|ortodontski pregled)$/, /^orthodontics?$/] },
    { key: 'emergencyDentalCare', patterns: [/^(?:hitna stomatoloska pomoc|hitna stomatologija)$/, /^emergency dental care$/] },
  ];
  const localeForFormatting = () => ({
    'sr-Latn': 'sr-Latn-RS',
    'sr-Cyrl': 'sr-Cyrl-RS',
    en: 'en-US',
    ru: 'ru-RU',
    de: 'de-DE',
  }[i18n.getLocale()] || 'sr-Latn-RS');

  function getErrorMessage(code) {
    const translated = code ? t(`booking.errors.${code}`) : '';
    return translated && translated !== `booking.errors.${code}`
      ? translated
      : t('booking.genericError');
  }

  function getEmailDeliveryMessages(delivery) {
    if (!delivery || typeof delivery !== 'object') return [];
    return ['customer', 'clinic'].flatMap((recipient) => {
      const status = delivery[recipient];
      if (!['sent', 'failed', 'not_configured'].includes(status)) return [];
      const keyStatus = status === 'not_configured' ? 'NotConfigured' : status[0].toUpperCase() + status.slice(1);
      return [t(`booking.success.email.${recipient}${keyStatus}`)];
    });
  }

  function getServiceDisplayName(service) {
    const rawName = service?.name ?? service?.service_name ?? service?.title ?? t('booking.ui.serviceFallback');
    const normalized = String(rawName)
      .toLocaleLowerCase()
      .replace(/[а-яђјљњћџ]/g, (character) => cyrillicToLatin[character] || character)
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .trim();
    const match = serviceNameKeys.find(({ patterns }) => patterns.some((pattern) => pattern.test(normalized)));
    return match ? t(`booking.serviceNames.${match.key}`) : rawName;
  }

  function getServiceCategory(service) {
    if (typeof service.category === 'string' && service.category.trim()) return service.category.trim();
    const name = String(service.name || '')
      .toLocaleLowerCase()
      .replace(/[а-яђјљњћџ]/g, (character) => cyrillicToLatin[character] || character)
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
    if (/implant|imlant/.test(name)) return 'Implantologija';
    if (/izbelj|beljen|faset|vinir|whitening|cosmetic/.test(name)) return 'Estetska stomatologija';
    if (/ortodont|alajner|fixed brace/.test(name)) return 'Ortodoncija';
    if (/kamen|fisur|fluor|higijen|pregled|consult/.test(name)) return 'Preventiva i higijena';
    if (/vadjen|vadjenje|hirur|apikot|extraction/.test(name)) return 'Oralna hirurgija';
    if (/kanal|zivc|endodont|plomb|poprav|root canal|filling/.test(name)) return 'Konzervativa i endodoncija';
    if (/parodont|kiret|period/.test(name)) return 'Parodontologija';
    if (/krun|most|proteze|crown|bridge|prosthodont/.test(name)) return 'Protetika';
    return 'Ostale usluge';
  }

  function setServicePickerOpen(open, focusSearch = false) {
    if (!servicePickerPanel || !servicePickerTrigger) return;
    servicePickerPanel.hidden = !open;
    servicePickerTrigger.setAttribute('aria-expanded', String(open));
    servicePicker?.classList.toggle('is-open', open);
    if (open) {
      const triggerRect = servicePickerTrigger.getBoundingClientRect();
      const panelHeight = servicePickerPanel.getBoundingClientRect().height;
      const spaceAbove = triggerRect.top;
      const spaceBelow = window.innerHeight - triggerRect.bottom;
      servicePicker?.classList.toggle(
        'opens-up',
        spaceBelow < panelHeight && spaceAbove > spaceBelow
      );
    } else {
      servicePicker?.classList.remove('opens-up');
    }
    if (open && focusSearch) serviceSearch?.focus();
  }

  function renderServicePickerOptions(services = state.services) {
    if (!servicePickerOptions) return;
    const query = serviceSearch?.value.trim().toLocaleLowerCase() || '';
    const filtered = services.filter((service) =>
      [service.name, service.description, getServiceCategory(service)]
        .join(' ')
        .toLocaleLowerCase()
        .includes(query)
    );
    servicePickerOptions.replaceChildren();
    const groups = new Map();
    filtered.forEach((service) => {
      const category = getServiceCategory(service);
      if (!groups.has(category)) groups.set(category, []);
      groups.get(category).push(service);
    });

    const categoryOrder = [
      'Preventiva i higijena', 'Konzervativa i endodoncija', 'Oralna hirurgija',
      'Protetika', 'Implantologija', 'Estetska stomatologija', 'Ortodoncija',
      'Parodontologija', 'Ostale usluge',
    ];
    [...groups.entries()]
      .sort(([first], [second]) => categoryOrder.indexOf(first) - categoryOrder.indexOf(second))
      .forEach(([category, items]) => {
        const group = document.createElement('div');
        group.className = 'service-picker-group';
        group.setAttribute('role', 'group');
        group.setAttribute('aria-label', category);
        const heading = document.createElement('div');
        heading.className = 'service-picker-group__heading';
        heading.textContent = category;
        group.append(heading);

        items.forEach((service) => {
          const serviceId = String(service.id ?? service.service_id ?? '');
          const selected = serviceId === serviceSelect.value;
          const duration = Number(service.duration_minutes);
          const option = document.createElement('button');
          option.type = 'button';
          option.className = 'service-picker-option';
          option.setAttribute('role', 'option');
          option.setAttribute('aria-selected', String(selected));
          const copy = document.createElement('span');
          copy.className = 'service-picker-option__copy';
          const name = document.createElement('strong');
          name.textContent = getServiceDisplayName(service);
          const description = document.createElement('small');
          description.textContent = service.description || '';
          copy.append(name);
          if (description.textContent) copy.append(description);
          const badge = document.createElement('span');
          badge.className = 'service-duration-badge';
          badge.textContent = duration > 0
            ? `${duration} ${t('booking.ui.durationUnit')}`
            : t('booking.ui.durationNotAvailable');
          option.append(copy, badge);
          option.addEventListener('click', () => {
            serviceSelect.value = serviceId;
            serviceSelect.dispatchEvent(new Event('change', { bubbles: true }));
            serviceSearch.value = '';
            setServicePickerOpen(false);
            servicePickerTrigger.focus();
          });
          option.addEventListener('keydown', (event) => {
            if (event.key !== 'ArrowDown' && event.key !== 'ArrowUp') return;
            event.preventDefault();
            const options = [...servicePickerOptions.querySelectorAll('[role="option"]')];
            const next = options.indexOf(option) + (event.key === 'ArrowDown' ? 1 : -1);
            options[Math.max(0, Math.min(options.length - 1, next))]?.focus();
          });
          group.append(option);
        });
        servicePickerOptions.append(group);
      });

    if (!filtered.length) {
      const empty = document.createElement('p');
      empty.className = 'service-picker-empty';
      empty.textContent = t('booking.ui.serviceNoResults');
      servicePickerOptions.append(empty);
    }
  }

  function updateServicePicker(services = state.services) {
    if (!servicePickerTrigger || !selectedServiceName) return;
    const selected = services.find(
      (service) => String(service.id ?? service.service_id) === serviceSelect.value
    );
    if (selected) {
      selectedServiceName.textContent = getServiceDisplayName(selected);
      selectedServiceCategory.textContent = getServiceCategory(selected);
      selectedServiceCategory.hidden = false;
      selectedServiceDuration.textContent = `${selected.duration_minutes} ${t('booking.ui.durationUnit')}`;
      selectedServiceDuration.hidden = false;
    } else {
      selectedServiceName.textContent = t('booking.ui.serviceOption');
      selectedServiceCategory.textContent = '';
      selectedServiceCategory.hidden = true;
      selectedServiceDuration.textContent = '';
      selectedServiceDuration.hidden = true;
    }
    servicePickerTrigger.disabled = serviceSelect.disabled;
    if (servicePickerPanel && !servicePickerPanel.hidden) renderServicePickerOptions(services);
  }

  function renderSuccess(result) {
    if (!result) return;
    const statusKey = result.status === 'confirmed'
      ? 'booking.success.statusConfirmed'
      : result.status === 'cancelled'
        ? 'booking.success.statusCancelled'
        : 'booking.success.statusPending';
    const details = [
      t(successMessageKeys[result.messageCode] || 'booking.success.received'),
      `${t('booking.success.statusLabel')}: ${t(statusKey)}.`,
      ...getEmailDeliveryMessages(result.emailDelivery),
    ];
    setStatusPanel(successPanel, true, details.join(' '));
    setStatusPanelTitle(successPanel, t('booking.success.title'));
    setStatus(t('booking.success.notification'), 'success');
  }

  function setStatusPanelTitle(panel, value) {
    const title = panel?.querySelector('strong');
    if (title) title.textContent = value;
  }

  function getTodayString() {
    const parts = new Intl.DateTimeFormat('en-CA', {
      timeZone: 'Europe/Belgrade',
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).formatToParts(new Date());
    const values = Object.fromEntries(parts.map((part) => [part.type, part.value]));
    return `${values.year}-${values.month}-${values.day}`;
  }

  function parseDate(value) {
    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day);
  }

  function monthStart(date) {
    return new Date(date.getFullYear(), date.getMonth(), 1);
  }

  function calendarMonthRange() {
    const year = state.calendarMonth.getFullYear();
    const month = state.calendarMonth.getMonth();
    return {
      start: getLocalDateString(new Date(year, month, 1)),
      end: getLocalDateString(new Date(year, month + 1, 0)),
    };
  }

  function setStatus(message, type = 'info') {
    if (!statusText) {
      return;
    }

    statusText.textContent = message || '';
    statusText.dataset.type = type;
  }

  function setLocalizedStatus(key, params = {}, type = 'info') {
    state.statusMessage = { key, params, type };
    setStatus(t(`booking.messages.${key}`, params), type);
  }

  function setStatusPanel(panel, visible, message = '') {
    if (!panel) {
      return;
    }

    panel.hidden = !visible;
    panel.replaceChildren();
    if (!visible) {
      return;
    }

    const title = document.createElement('strong');
    title.textContent = panel === successPanel ? t('booking.success.title') : t('booking.errorTitle');
    const detail = document.createElement('p');
    detail.textContent = message;
    panel.append(title, detail);
  }

  function clearFieldError(fieldName) {
    const field = document.getElementById(fieldName);
    const error = document.querySelector(`[data-error-for="${fieldName}"]`);
    state.fieldErrorKeys.delete(fieldName);
    if (field) {
      field.closest('.field')?.classList.remove('has-error');
    }
    if (error) {
      error.textContent = '';
    }
  }

  function setFieldError(fieldName, messageKey) {
    const field = document.getElementById(fieldName);
    const error = document.querySelector(`[data-error-for="${fieldName}"]`);
    if (field) {
      field.closest('.field')?.classList.add('has-error');
    }
    if (error) {
      state.fieldErrorKeys.set(fieldName, messageKey);
      error.textContent = t(`booking.messages.${messageKey}`);
    }
  }

  function normalizeCollection(payload) {
    if (Array.isArray(payload)) {
      return payload;
    }

    if (payload && Array.isArray(payload.data)) {
      return payload.data;
    }

    return [];
  }

  function getSelectedClinicId() {
    if (clinicSelect && clinicSelect.value) {
      return Number(clinicSelect.value);
    }

    if (state.clinics.length === 1) {
      return Number(state.clinics[0].id || state.clinics[0].clinic_id || 0);
    }

    return null;
  }

  function getLocalDateString(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
  }

  state.calendarMonth = monthStart(parseDate(getTodayString()));

  function renderCalendar() {
    if (!calendarGrid || !state.calendarMonth) {
      return;
    }

    const month = state.calendarMonth;
    const year = month.getFullYear();
    const monthIndex = month.getMonth();
    const daysInMonth = new Date(year, monthIndex + 1, 0).getDate();
    const firstWeekday = (new Date(year, monthIndex, 1).getDay() + 6) % 7;
    const today = getTodayString();
    const selectedDate = dateInput?.value || '';
    const monthTitle = new Intl.DateTimeFormat(localeForFormatting(), {
      month: 'long',
      year: 'numeric',
    }).format(month);

    calendarMonthLabel.textContent = monthTitle;
    calendarGrid.replaceChildren();
    calendarGrid.setAttribute('aria-busy', String(state.calendarLoading));
    previousMonthButton.disabled = monthStart(month) <= monthStart(parseDate(today));
    nextMonthButton.disabled = false;

    for (let blank = 0; blank < firstWeekday; blank += 1) {
      const spacer = document.createElement('span');
      spacer.className = 'calendar-day-spacer';
      spacer.setAttribute('aria-hidden', 'true');
      calendarGrid.appendChild(spacer);
    }

    for (let day = 1; day <= daysInMonth; day += 1) {
      const date = getLocalDateString(new Date(year, monthIndex, day));
      const available = state.dateAvailability.get(date) === true;
      const isPast = date < today;
      const isToday = date === today;
      const isSelected = date === selectedDate;
      const button = document.createElement('button');

      button.type = 'button';
      button.className = 'calendar-day';
      button.textContent = String(day);
      button.disabled = state.calendarLoading || isPast || !available;
      button.classList.toggle('is-available', available && !isPast);
      button.classList.toggle('is-unavailable', !available || isPast);
      button.classList.toggle('is-today', isToday);
      button.classList.toggle('is-selected', isSelected);
      button.setAttribute('aria-pressed', String(isSelected));
      button.setAttribute(
        'aria-label',
        `${new Intl.DateTimeFormat(localeForFormatting(), {
          weekday: 'long',
          month: 'long',
          day: 'numeric',
          year: 'numeric',
        }).format(parseDate(date))}${isToday ? `, ${t('booking.today')}` : ''}, ${isPast || !available ? t('booking.unavailable') : t('booking.available')}`
      );

      if (available && !isPast) {
        const marker = document.createElement('span');
        marker.className = 'calendar-day-marker';
        marker.setAttribute('aria-hidden', 'true');
        button.appendChild(marker);
      }

      button.addEventListener('click', () => {
        if (button.disabled) {
          return;
        }

        dateInput.value = date;
        clearFieldError('appointment_date');
        renderCalendar();
        renderTimeSlots([], 'loadingSlots');
        updateSummary();
        loadTimeSlots(date);
      });

      calendarGrid.appendChild(button);
    }
  }

  function disableSubmit(disabled) {
    if (!submitButton) {
      return;
    }

    submitButton.disabled = disabled;
    submitButton.setAttribute('aria-busy', String(disabled));
  }

  function formatDateLabel(value) {
    if (!value) {
      return t('booking.chooseDate');
    }

    const date = new Date(`${value}T00:00:00`);
    if (Number.isNaN(date.getTime())) {
      return value;
    }

    return new Intl.DateTimeFormat(localeForFormatting(), {
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    }).format(date);
  }

  function formatTimeLabel(value) {
    if (!value) {
      return t('booking.chooseTime');
    }

    const [hours, minutes] = value.split(':').map(Number);
    const date = new Date();
    date.setHours(hours, minutes, 0, 0);

    return new Intl.DateTimeFormat(localeForFormatting(), {
      hour: 'numeric',
      minute: '2-digit',
    }).format(date);
  }

  function updateSummary() {
    const clinicId = getSelectedClinicId();
    const selectedClinic = state.clinics.find(
      (clinic) => Number(clinic.id ?? clinic.clinic_id) === clinicId
    );
    const selectedService = state.services.find(
      (service) => String(service.id ?? service.service_id) === serviceSelect?.value
    );
    const duration = Number(selectedService?.duration_minutes);
    const durationLabel = Number.isFinite(duration) && duration > 0
      ? `${duration} ${t('booking.ui.durationUnit')}`
      : t('booking.ui.durationNotAvailable');

    if (summaryClinic) {
      summaryClinic.textContent = selectedClinic
        ? selectedClinic.name ?? selectedClinic.clinic_name ?? t('booking.ui.clinicFallback')
        : t('booking.ui.chooseClinic');
    }

    if (summaryService) {
      summaryService.textContent = selectedService
        ? getServiceDisplayName(selectedService)
        : t('booking.ui.chooseService');
    }

    if (summaryDuration) {
      summaryDuration.textContent = duration > 0 ? durationLabel : '—';
    }

    if (serviceDuration) {
      serviceDuration.textContent = selectedService
        ? duration > 0 ? t('booking.ui.approxDuration', { duration: durationLabel }) : t('booking.ui.durationNotAvailable')
        : t('booking.ui.durationAfterSelection');
    }

    if (summaryDate) {
      summaryDate.textContent = formatDateLabel(dateInput?.value || '');
    }

    if (summaryTime) {
      summaryTime.textContent = formatTimeLabel(state.selectedTime || timeInput?.value || '');
    }

  }

  function setBookingStep(step, focusHeading = true) {
    if (![1, 2, 3].includes(step)) {
      return;
    }

    state.currentStep = step;
    if (bookingLayout) {
      bookingLayout.dataset.currentStep = String(step);
    }
    bookingPanels.forEach((panel) => {
      panel.hidden = Number(panel.dataset.bookingPanel) !== step;
    });
    bookingSteps.forEach((step) => {
      const stepNumber = Number(step.dataset.bookingStep);
      step.classList.toggle('is-complete', stepNumber < state.currentStep);
      if (stepNumber === state.currentStep) {
        step.setAttribute('aria-current', 'step');
      } else {
        step.removeAttribute('aria-current');
      }
    });

    state.statusMessage = null;
    setStatus('');
    setStatusPanel(errorPanel, false);

    if (focusHeading) {
      const heading = bookingPanels
        .find((panel) => Number(panel.dataset.bookingPanel) === step)
        ?.querySelector('h2');
      heading?.focus({ preventScroll: true });
      heading?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
  }

  function renderTimeSlots(slots, emptyMessageKey = 'noSlotsForDate') {
    if (!timePicker) {
      return;
    }

    timePicker.innerHTML = '';
    const emptyMessage = t(`booking.messages.${emptyMessageKey}`);
    state.timeEmptyMessageKey = emptyMessageKey;
    timePicker.setAttribute('aria-busy', String(
      emptyMessageKey === 'loadingSlots' || emptyMessageKey === 'refreshingSlots'
    ));
    state.selectedTime = '';
    if (timeInput) timeInput.value = '';

    if (!slots.length) {
      const empty = document.createElement('p');
      empty.className = 'time-slot-empty';
      empty.textContent = emptyMessage;
      timePicker.appendChild(empty);
      timeInput && (timeInput.value = '');
      updateSummary();
      return;
    }

    slots.forEach((slot) => {
      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'time-slot';
      button.textContent = formatTimeLabel(slot);
      button.setAttribute('data-slot', slot);
      button.setAttribute('aria-pressed', 'false');
      button.addEventListener('click', () => {
        state.selectedTime = slot;
        if (timeInput) timeInput.value = slot;
        timePicker.querySelectorAll('.time-slot').forEach((item) => {
          const isSelected = item === button;
          item.classList.toggle('is-selected', isSelected);
          item.setAttribute('aria-pressed', String(isSelected));
        });
        clearFieldError('appointment_time');
        state.statusMessage = null;
        setStatus('');
        updateSummary();
      });
      timePicker.appendChild(button);
    });

    updateSummary();
  }

  function populateServices(items) {
    state.allServices = items;
    state.servicesLoading = false;
    state.servicesLoadFailed = false;
    renderServicesForClinic();
  }

  function renderServicesForClinic() {
    if (!serviceSelect) {
      return;
    }

    const previousServiceId = serviceSelect.value;
    const clinicId = getSelectedClinicId();
    if (state.servicesLoading || state.servicesLoadFailed) {
      const option = document.createElement('option');
      option.value = '';
      option.textContent = t(state.servicesLoadFailed ? 'booking.ui.servicesUnavailable' : 'booking.ui.loadingServices');
      serviceSelect.replaceChildren(option);
      serviceSelect.disabled = true;
      state.services = [];
      updateServicePicker();
      return;
    }
    const hasClinicIds = state.allServices.some((service) => service.clinic_id !== undefined);
    const services = clinicId && hasClinicIds
      ? state.allServices.filter((service) => Number(service.clinic_id) === clinicId)
      : state.allServices;
    state.services = services;
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = t('booking.ui.serviceOption');
    serviceSelect.replaceChildren(placeholder);

    if (!services.length) {
      const option = document.createElement('option');
      option.textContent = clinicId
        ? t('booking.ui.noServicesAtClinic')
        : t('booking.ui.chooseClinicFirst');
      serviceSelect.appendChild(option);
      serviceSelect.disabled = true;
      updateServicePicker(services);
      return;
    }

    services.forEach((service) => {
      const option = document.createElement('option');
      option.value = service.id ?? service.service_id ?? '';
      const name = getServiceDisplayName(service);
      const duration = Number(service.duration_minutes);
      option.textContent = duration > 0 ? `${name} · ${duration} ${t('booking.ui.durationUnit')}` : name;
      serviceSelect.appendChild(option);
    });

    serviceSelect.disabled = !clinicId && state.clinics.length > 1;
    if (services.some((service) => String(service.id ?? service.service_id) === previousServiceId)) {
      serviceSelect.value = previousServiceId;
    }
    updateServicePicker(services);
    updateSummary();
  }

  function populateClinics(items) {
    state.clinics = items;
    state.clinicsLoading = false;
    state.clinicsLoadFailed = false;

    if (!clinicSelect) {
      return;
    }

    const requestedClinicId = new URLSearchParams(window.location.search).get('clinic_id');
    const placeholder = document.createElement('option');
    placeholder.value = '';
    placeholder.textContent = t('booking.ui.clinicOption');
    clinicSelect.replaceChildren(placeholder);

    if (!items.length) {
      clinicSelect.disabled = true;
      if (clinicContainer) {
        clinicContainer.hidden = true;
      }
      setLocalizedStatus('noClinics', {}, 'warning');
      disableSubmit(true);
      return;
    }

    if (items.length === 1) {
      const clinic = items[0];
      const option = document.createElement('option');
      option.value = clinic.id ?? clinic.clinic_id ?? '';
      option.textContent = clinic.name ?? clinic.clinic_name ?? t('booking.ui.clinicFallback');
      clinicSelect.replaceChildren(option);
      clinicSelect.disabled = true;
      if (clinicContainer) {
        clinicContainer.hidden = true;
      }
    } else {
      items.forEach((clinic) => {
        const option = document.createElement('option');
        option.value = clinic.id ?? clinic.clinic_id ?? '';
        option.textContent = clinic.name ?? clinic.clinic_name ?? t('booking.ui.clinicFallback');
        clinicSelect.appendChild(option);
      });
      if (requestedClinicId && items.some((clinic) => String(clinic.id ?? clinic.clinic_id ?? '') === requestedClinicId)) {
        clinicSelect.value = requestedClinicId;
      }
      clinicSelect.disabled = false;
      if (clinicContainer) {
        clinicContainer.hidden = false;
      }
    }
  }

  async function loadServices() {
    try {
      const request = await fetch('backend/services.php', {
        headers: { Accept: 'application/json' },
      });

      if (!request.ok) {
        throw new Error('Service load failed');
      }

      const payload = await request.json();
      const services = normalizeCollection(payload);
      populateServices(services);
      return services;
    } catch (error) {
      console.error(error);
      state.servicesLoading = false;
      state.servicesLoadFailed = true;
      if (serviceSelect) {
        serviceSelect.innerHTML = `<option value="">${t('booking.ui.servicesUnavailable')}</option>`;
        serviceSelect.disabled = true;
      }
      setLocalizedStatus('servicesLoadFailed', {}, 'error');
      disableSubmit(true);
      return [];
    }
  }

  async function loadClinics() {
    try {
      const request = await fetch('backend/clinics.php', {
        headers: { Accept: 'application/json' },
      });

      if (!request.ok) {
        throw new Error('Clinic load failed');
      }

      const payload = await request.json();
      const clinics = normalizeCollection(payload);
      populateClinics(clinics);
      return clinics;
    } catch (error) {
      console.error(error);
      state.clinicsLoading = false;
      state.clinicsLoadFailed = true;
      if (clinicSelect) {
        clinicSelect.innerHTML = `<option value="">${t('booking.ui.clinicUnavailable')}</option>`;
        clinicSelect.disabled = true;
      }
      setLocalizedStatus('clinicLoadFailed', {}, 'error');
      disableSubmit(true);
      return [];
    }
  }

  function setCalendarStatus(message, type = 'info') {
    if (!calendarStatus) {
      return;
    }
    calendarStatus.textContent = message;
    calendarStatus.dataset.type = type;
  }

  function setCalendarStatusKey(key, params = {}, type = 'info') {
    state.calendarMessage = { key, params, type };
    setCalendarStatus(t(`booking.messages.${key}`, params), type);
  }

  function clearAppointmentSelection() {
    dateInput.value = '';
    state.selectedTime = '';
    state.slotRequest += 1;
    renderCalendar();
    renderTimeSlots([], 'chooseAllForSlots');
    clearFieldError('appointment_date');
    clearFieldError('appointment_time');
    updateSummary();
  }

  async function refreshCalendar({ reloadSlots = false } = {}) {
    const clinicId = getSelectedClinicId();
    const selectedServiceId = serviceSelect && serviceSelect.value ? Number(serviceSelect.value) : null;
    const requestId = ++state.calendarRequest;

    state.dateAvailability.clear();
    if (!clinicId || !selectedServiceId) {
      state.calendarLoading = false;
      renderCalendar();
      setCalendarStatusKey('chooseClinicServiceDates');
      renderTimeSlots([], 'chooseAllForSlots');
      return;
    }

    const { start, end } = calendarMonthRange();
    state.calendarLoading = true;
    renderCalendar();
    setCalendarStatusKey('loadingDates', {}, 'loading');

    try {
      const url = new URL('backend/availability.php', window.location.href);
      url.searchParams.set('clinic_id', String(clinicId));
      url.searchParams.set('service_id', String(selectedServiceId));
      url.searchParams.set('start_date', start);
      url.searchParams.set('end_date', end);

      const request = await fetch(url, {
        headers: { Accept: 'application/json' },
      });

      if (!request.ok) {
        const errorPayload = await request.json().catch(() => ({}));
        throw new Error(errorPayload.message || 'Nije moguće proveriti dostupne datume.');
      }

      const payload = await request.json();
      if (
        !payload.success ||
        !Array.isArray(payload.dates) ||
        payload.start_date !== start ||
        payload.end_date !== end
      ) {
        throw new Error(payload.message || 'Odgovor o dostupnosti nije potpun.');
      }

      if (requestId !== state.calendarRequest) {
        return;
      }

      payload.dates.forEach((day) => {
        if (typeof day.date === 'string' && typeof day.available === 'boolean') {
          state.dateAvailability.set(day.date, day.available);
        }
      });
      state.calendarLoading = false;

      const selectedDate = dateInput.value;
      if (selectedDate && state.dateAvailability.get(selectedDate) !== true) {
        clearAppointmentSelection();
      } else {
        renderCalendar();
      }

      const availableCount = [...state.dateAvailability.values()].filter(Boolean).length;
      setCalendarStatusKey(
        availableCount ? 'availableDays' : 'noDatesThisMonth',
        availableCount ? { count: availableCount } : {},
        availableCount ? 'success' : 'warning'
      );

      if (reloadSlots && dateInput.value) {
        await loadTimeSlots(dateInput.value);
      }
    } catch (error) {
      if (requestId !== state.calendarRequest) {
        return;
      }

      console.error(error);
      state.calendarLoading = false;
      state.dateAvailability.clear();
      renderCalendar();
      setCalendarStatusKey('datesLoadFailed', {}, 'error');
      renderTimeSlots([], 'slotsLoadFailed');
    }
  }

  async function loadTimeSlots(selectedDate) {
    const clinicId = getSelectedClinicId();
    const selectedServiceId = serviceSelect && serviceSelect.value ? Number(serviceSelect.value) : null;
    if (!clinicId || !selectedServiceId || !selectedDate) {
      renderTimeSlots([], 'chooseAllForSlots');
      return;
    }

    const requestId = ++state.slotRequest;
    renderTimeSlots([], 'loadingSlots');

    try {
      const url = new URL('backend/availability.php', window.location.href);
      url.searchParams.set('clinic_id', String(clinicId));
      url.searchParams.set('service_id', String(selectedServiceId));
      url.searchParams.set('date', selectedDate);
      const request = await fetch(url, {
        headers: { Accept: 'application/json' },
      });

      const payload = await request.json().catch(() => ({}));
      if (!request.ok || !payload.success || !Array.isArray(payload.slots)) {
        throw new Error(payload.message || 'Nije moguće učitati slobodne termine.');
      }
      if (requestId !== state.slotRequest || dateInput.value !== selectedDate) {
        return;
      }

      if (payload.slots.length === 0) {
        state.dateAvailability.set(selectedDate, false);
        dateInput.value = '';
        renderCalendar();
        renderTimeSlots([], 'noSlotsRemain');
        setCalendarStatusKey('chooseAnotherDate', {}, 'warning');
        clearFieldError('appointment_date');
      } else {
        renderTimeSlots(payload.slots);
        setLocalizedStatus('selectTimeForDate', { date: formatDateLabel(selectedDate) }, 'success');
      }
      updateSummary();
    } catch (error) {
      if (requestId !== state.slotRequest) {
        return;
      }
      console.error(error);
      renderTimeSlots([], 'slotsTryAgain');
      setCalendarStatusKey('slotsRetry', {}, 'error');
    }
  }

  function validateClientSide() {
    let valid = true;

    const nameValue = document.getElementById('patient_name')?.value.trim() || '';
    if (!nameValue) {
      setFieldError('patient_name', 'requiredName');
      valid = false;
    } else {
      clearFieldError('patient_name');
    }

    const emailValue = document.getElementById('patient_email')?.value.trim() || '';
    if (!emailValue) {
      setFieldError('patient_email', 'requiredEmail');
      valid = false;
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailValue)) {
      setFieldError('patient_email', 'invalidEmail');
      valid = false;
    } else {
      clearFieldError('patient_email');
    }

    const phoneValue = document.getElementById('patient_phone')?.value.trim() || '';
    if (!phoneValue) {
      setFieldError('patient_phone', 'requiredPhone');
      valid = false;
    } else {
      clearFieldError('patient_phone');
    }

    if (!serviceSelect || !serviceSelect.value) {
      setFieldError('service_id', 'requiredService');
      valid = false;
    } else {
      clearFieldError('service_id');
    }

    if (!getSelectedClinicId()) {
      setFieldError('clinic_id', 'requiredClinic');
      valid = false;
    } else {
      clearFieldError('clinic_id');
    }

    if (!dateInput || !dateInput.value || state.dateAvailability.get(dateInput.value) !== true) {
      setFieldError('appointment_date', 'requiredDate');
      valid = false;
    } else {
      clearFieldError('appointment_date');
    }

    if (!timeInput || !timeInput.value) {
      setFieldError('appointment_time', 'requiredTime');
      valid = false;
    } else {
      clearFieldError('appointment_time');
    }

    return valid;
  }

  function validateAppointmentChoice() {
    let valid = true;
    if (!getSelectedClinicId()) {
      setFieldError('clinic_id', 'requiredClinic');
      valid = false;
    } else {
      clearFieldError('clinic_id');
    }

    if (!serviceSelect?.value) {
      setFieldError('service_id', 'requiredService');
      valid = false;
    } else {
      clearFieldError('service_id');
    }

    if (!valid) {
      setLocalizedStatus('formInvalid', {}, 'error');
    }
    return valid;
  }

  function validateDateAndTime() {
    let valid = true;
    if (!dateInput?.value || state.dateAvailability.get(dateInput.value) !== true) {
      setFieldError('appointment_date', 'requiredDate');
      valid = false;
    } else {
      clearFieldError('appointment_date');
    }

    if (!timeInput?.value) {
      setFieldError('appointment_time', 'requiredTime');
      valid = false;
    } else {
      clearFieldError('appointment_time');
    }

    if (!valid) {
      setLocalizedStatus('formInvalid', {}, 'error');
    }
    return valid;
  }

  function advanceBookingStep() {
    if (state.currentStep === 1) {
      if (validateAppointmentChoice()) {
        setBookingStep(2);
      }
    } else if (state.currentStep === 2 && validateDateAndTime()) {
      setBookingStep(3);
    }
  }

  nextStepButtons.forEach((button) => {
    button.addEventListener('click', advanceBookingStep);
  });

  backStepButtons.forEach((button) => {
    button.addEventListener('click', () => {
      setBookingStep(Number(button.dataset.bookingBack));
    });
  });

  serviceSelect?.addEventListener('change', () => {
    updateServicePicker();
    clearFieldError('service_id');
    clearAppointmentSelection();
    refreshCalendar();
    updateSummary();
  });

  servicePickerTrigger?.addEventListener('click', () => {
    const shouldOpen = servicePickerPanel.hidden;
    if (shouldOpen) renderServicePickerOptions();
    setServicePickerOpen(shouldOpen, shouldOpen);
  });

  servicePickerTrigger?.addEventListener('keydown', (event) => {
    if (event.key !== 'ArrowDown') return;
    event.preventDefault();
    renderServicePickerOptions();
    setServicePickerOpen(true, true);
  });

  serviceSearch?.addEventListener('input', () => renderServicePickerOptions());
  serviceSearch?.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      setServicePickerOpen(false);
      servicePickerTrigger?.focus();
    } else if (event.key === 'ArrowDown') {
      event.preventDefault();
      servicePickerOptions?.querySelector('[role="option"]')?.focus();
    }
  });

  document.addEventListener('click', (event) => {
    if (servicePicker && !servicePicker.contains(event.target)) setServicePickerOpen(false);
  });

  clinicSelect?.addEventListener('change', () => {
    clearFieldError('clinic_id');
    renderServicesForClinic();
    clearAppointmentSelection();
    refreshCalendar();
  });

  ['patient_name', 'patient_email', 'patient_phone'].forEach((id) => {
    document.getElementById(id)?.addEventListener('input', updateSummary);
  });

  previousMonthButton?.addEventListener('click', () => {
    if (previousMonthButton.disabled) {
      return;
    }
    state.calendarMonth = new Date(
      state.calendarMonth.getFullYear(),
      state.calendarMonth.getMonth() - 1,
      1
    );
    clearAppointmentSelection();
    refreshCalendar();
  });

  nextMonthButton?.addEventListener('click', () => {
    state.calendarMonth = new Date(
      state.calendarMonth.getFullYear(),
      state.calendarMonth.getMonth() + 1,
      1
    );
    clearAppointmentSelection();
    refreshCalendar();
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    if (state.submitting) {
      return;
    }

    if (state.currentStep < 3) {
      advanceBookingStep();
      return;
    }

    if (!validateClientSide()) {
      if (!getSelectedClinicId() || !serviceSelect?.value) {
        setBookingStep(1);
      } else if (
        !dateInput?.value ||
        state.dateAvailability.get(dateInput.value) !== true ||
        !timeInput?.value
      ) {
        setBookingStep(2);
      }
      setLocalizedStatus('formInvalid', {}, 'error');
      return;
    }

    const selectedClinicId = getSelectedClinicId();
    const payload = {
      clinic_id: selectedClinicId,
      service_id: Number(serviceSelect.value),
      locale: i18n.getLocale(),
      patient_name: document.getElementById('patient_name').value.trim(),
      patient_email: document.getElementById('patient_email').value.trim(),
      patient_phone: document.getElementById('patient_phone').value.trim(),
      appointment_date: dateInput.value,
      appointment_time: timeInput.value,
    };

    state.submitting = true;
    disableSubmit(true);
    state.lastErrorCode = null;
    state.lastSuccess = null;
    setLocalizedStatus('sending', {}, 'info');
    setStatusPanel(errorPanel, false);
    setStatusPanel(successPanel, false);

    try {
      const request = await fetch('backend/appointments.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          Accept: 'application/json',
        },
        body: JSON.stringify(payload),
      });

      const response = await request.json().catch(() => ({ success: false }));

      if (!request.ok || !response.success) {
        throw Object.assign(new Error('Booking request failed'), { code: response.message });
      }

      state.lastSuccess = {
        messageCode: response.message,
        status: response.status,
        emailDelivery: response.email_delivery,
      };
      renderSuccess(state.lastSuccess);
      ['patient_name', 'patient_email', 'patient_phone'].forEach((id) => {
        const field = document.getElementById(id);
        if (field) {
          field.value = '';
        }
      });
      state.selectedTime = '';
      if (timeInput) timeInput.value = '';
      renderTimeSlots([], 'refreshingSlots');
      updateSummary();
      await refreshCalendar({ reloadSlots: true });
    } catch (error) {
      console.error(error);
      state.lastErrorCode = error.code || 'GENERIC';
      const message = getErrorMessage(error.code);
      setStatusPanel(errorPanel, true, message);
      setStatus(message, 'error');
    } finally {
      state.submitting = false;
      disableSubmit(false);
    }
  });

  Promise.all([loadServices(), loadClinics()]).then(() => {
    renderServicesForClinic();
    const ready = state.services.length > 0 && (state.clinics.length > 0 || !clinicSelect);
    disableSubmit(!ready);
    renderCalendar();
    refreshCalendar();
    updateSummary();
  });

  window.addEventListener('smilecraft:localechange', () => {
    const emptyClinic = clinicSelect?.querySelector('option[value=""]');
    if (emptyClinic) {
      const key = state.clinicsLoading
        ? 'booking.ui.loadingClinic'
        : state.clinicsLoadFailed
          ? 'booking.ui.clinicUnavailable'
          : 'booking.ui.clinicOption';
      emptyClinic.textContent = t(key);
    }
    renderServicesForClinic();
    renderCalendar();
    timePicker?.querySelectorAll('[data-slot]').forEach((button) => {
      button.textContent = formatTimeLabel(button.dataset.slot);
    });
    if (state.timeEmptyMessageKey && !timePicker?.querySelector('[data-slot]')) {
      renderTimeSlots([], state.timeEmptyMessageKey);
    }
    if (state.calendarMessage) {
      setCalendarStatusKey(
        state.calendarMessage.key,
        state.calendarMessage.params,
        state.calendarMessage.type
      );
    }
    if (state.fieldErrorKeys.size) {
      state.fieldErrorKeys.forEach((key, fieldName) => {
        const error = document.querySelector(`[data-error-for="${fieldName}"]`);
        if (error) error.textContent = t(`booking.messages.${key}`);
      });
    }
    updateSummary();
    if (state.lastSuccess) renderSuccess(state.lastSuccess);
    else if (state.lastErrorCode) {
      const message = getErrorMessage(state.lastErrorCode);
      setStatusPanel(errorPanel, true, message);
      setStatus(message, 'error');
    } else if (state.statusMessage) {
      setLocalizedStatus(
        state.statusMessage.key,
        state.statusMessage.params,
        state.statusMessage.type
      );
    }
  });

  renderCalendar();
  setCalendarStatusKey('chooseClinicServiceDates');
  updateSummary();
});
