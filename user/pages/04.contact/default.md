---
title: 'Contact Us'
menu: Contact
metadata:
    description: 'Contact Ogle County Economic Development Corporation. Get personalized assistance for site selection, business development, incentives, and more. Call (815) 555-1234 or email us.'
    keywords: 'contact OCEDC, Ogle County contact, economic development contact, business assistance, site selection help, Oregon Illinois'
    robots: 'index, follow'
form:
    name: contact-form
    fields:
        -
            name: name
            label: 'Full Name'
            placeholder: 'Enter your full name'
            autocomplete: 'on'
            type: text
            validate:
                required: true
        -
            name: company
            label: Company/Organization
            placeholder: 'Your company name (optional)'
            type: text
        -
            name: email
            label: 'Email Address'
            placeholder: your.email@example.com
            type: email
            validate:
                required: true
        -
            name: phone
            label: 'Phone Number'
            placeholder: '(815) 555-1234'
            type: tel
        -
            name: inquiry_type
            label: 'Type of Inquiry'
            type: select
            options:
                general: 'General Information'
                site_selection: 'Site Selection'
                expansion: 'Business Expansion'
                relocation: 'Business Relocation'
                incentives: 'Incentives & Financing'
                tour: 'Schedule a Tour'
                other: Other
            validate:
                required: true
        -
            name: message
            label: Message
            placeholder: 'Please tell us about your project or inquiry...'
            type: textarea
            rows: 6
            validate:
                required: true
        -
            name: honeypot
            type: honeypot
    buttons:
        -
            type: submit
            value: 'Send Message'
            classes: 'btn btn-primary'
        -
            type: reset
            value: 'Clear Form'
            classes: 'btn btn-secondary'
    process:
        -
            email:
                from: '{{ config.plugins.email.from }}'
                to: '{{ config.plugins.email.to }}'
                subject: '[OCEDC Website] New Contact Form Submission'
                body: '{% include "forms/data.html.twig" %}'
        -
            save:
                fileprefix: contact-
                dateformat: Ymd-His-u
                extension: txt
                body: '{% include "forms/data.txt.twig" %}'
        -
            message: 'Thank you for contacting OCEDC! We will respond to your inquiry within 24 hours.'
        -
            display: thankyou
---

# Contact Ogle County Economic Development Corporation

We're here to help your business succeed in Ogle County. Contact us for personalized assistance with site selection, business development, incentives, and more.

## Office Information

**Ogle County Economic Development Corporation**

123 Main Street  
Oregon, IL 61061

**Phone:** (815) 555-1234  
**Email:** info@oglecoedg.org  
**Hours:** Monday - Friday, 8:00 AM - 5:00 PM

## Get in Touch

### Business Inquiries

Interested in locating or expanding your business in Ogle County? We provide:

- **Free, Confidential Consultations** - Discuss your project needs
- **Site Selection Assistance** - Find the perfect location
- **Incentive Identification** - Maximize available programs
- **Resource Connections** - Connect with key stakeholders

### Site Tours

Schedule a personalized tour of available properties. We'll show you sites that match your requirements and discuss development opportunities.

### General Information

Have questions about doing business in Ogle County? We're happy to provide information about:

- Available sites and properties
- Tax incentives and financing programs
- Workforce and training resources
- Permits and regulations
- Community amenities

## Contact Form

Please fill out the form below and we'll get back to you within 24 hours. All inquiries are kept confidential.

{{ grav.twig.content|raw }}

---

**Prefer to contact us directly?**
📞 Phone: **(815) 555-1234**
✉️ Email: **info@oglecoedg.org**

## Service Area

We serve all of Ogle County, including:

- Oregon
- Rochelle
- Byron
- Polo
- Mount Morris
- And surrounding communities

## What to Expect

When you contact OCEDC, you can expect:

- **Prompt Response** - We'll respond within 24 hours
- **Confidential Service** - All inquiries are kept confidential
- **Expert Guidance** - Experienced economic development professionals
- **No Cost** - Our services are free of charge
- **Personalized Attention** - Tailored assistance for your needs

## Visit Us

Our office is located in downtown Oregon, Illinois. We welcome visitors by appointment to ensure we can give you our full attention.

[Schedule a Meeting](/contact) or call us to arrange a visit.

---

*Ready to explore opportunities in Ogle County? Contact us today - we're here to help your business succeed.*

