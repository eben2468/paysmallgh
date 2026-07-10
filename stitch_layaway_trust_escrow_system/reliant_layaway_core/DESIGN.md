---
name: Reliant Layaway Core
colors:
  surface: '#f9f9ff'
  surface-dim: '#d2daf0'
  surface-bright: '#f9f9ff'
  surface-container-lowest: '#ffffff'
  surface-container-low: '#f1f3ff'
  surface-container: '#e9edff'
  surface-container-high: '#e0e8ff'
  surface-container-highest: '#dbe2f9'
  on-surface: '#141b2c'
  on-surface-variant: '#3f4945'
  inverse-surface: '#293041'
  inverse-on-surface: '#edf0ff'
  outline: '#707975'
  outline-variant: '#bfc9c4'
  surface-tint: '#29695b'
  primary: '#00342b'
  on-primary: '#ffffff'
  primary-container: '#004d40'
  on-primary-container: '#7ebdac'
  inverse-primary: '#94d3c1'
  secondary: '#785900'
  on-secondary: '#ffffff'
  secondary-container: '#fdc003'
  on-secondary-container: '#6c5000'
  tertiary: '#003608'
  on-tertiary: '#ffffff'
  tertiary-container: '#004f11'
  on-tertiary-container: '#72c26e'
  error: '#ba1a1a'
  on-error: '#ffffff'
  error-container: '#ffdad6'
  on-error-container: '#93000a'
  primary-fixed: '#afefdd'
  primary-fixed-dim: '#94d3c1'
  on-primary-fixed: '#00201a'
  on-primary-fixed-variant: '#065043'
  secondary-fixed: '#ffdf9e'
  secondary-fixed-dim: '#fabd00'
  on-secondary-fixed: '#261a00'
  on-secondary-fixed-variant: '#5b4300'
  tertiary-fixed: '#a3f69c'
  tertiary-fixed-dim: '#88d982'
  on-tertiary-fixed: '#002204'
  on-tertiary-fixed-variant: '#005312'
  background: '#f9f9ff'
  on-background: '#141b2c'
  surface-variant: '#dbe2f9'
typography:
  display-lg:
    fontFamily: Hanken Grotesk
    fontSize: 48px
    fontWeight: '700'
    lineHeight: 60px
    letterSpacing: -0.02em
  headline-lg:
    fontFamily: Hanken Grotesk
    fontSize: 32px
    fontWeight: '600'
    lineHeight: 40px
  headline-lg-mobile:
    fontFamily: Hanken Grotesk
    fontSize: 28px
    fontWeight: '600'
    lineHeight: 36px
  title-md:
    fontFamily: Hanken Grotesk
    fontSize: 20px
    fontWeight: '600'
    lineHeight: 28px
  body-md:
    fontFamily: Hanken Grotesk
    fontSize: 16px
    fontWeight: '400'
    lineHeight: 24px
  label-sm:
    fontFamily: JetBrains Mono
    fontSize: 12px
    fontWeight: '500'
    lineHeight: 16px
    letterSpacing: 0.05em
  currency-display:
    fontFamily: Hanken Grotesk
    fontSize: 24px
    fontWeight: '700'
    lineHeight: 32px
rounded:
  sm: 0.25rem
  DEFAULT: 0.5rem
  md: 0.75rem
  lg: 1rem
  xl: 1.5rem
  full: 9999px
spacing:
  unit: 4px
  xs: 4px
  sm: 8px
  md: 16px
  lg: 24px
  xl: 32px
  container-margin: 20px
  gutter: 16px
---

## Brand & Style

The brand personality is anchored in **reliability, financial empowerment, and transparency**. As a layaway platform, the design must bridge the gap between aspirational purchasing and disciplined saving. The target audience includes everyday Ghanaian consumers, from market traders to young professionals, requiring a UI that feels sophisticated yet profoundly accessible.

The design style is **Corporate Modern with Tactile Accents**. It utilizes a clean, systematic layout inspired by international fintech standards but softens the experience with warm, high-contrast action points that feel human and local. The emotional response should be one of "controlled progress"—users should feel that their money is safe and their goals are within reach. High-quality whitespace and clear visual hierarchies eliminate the anxiety often associated with financial commitments.

## Colors

The palette is led by **Deep Forest Green** (#004D40), a color associated with stability, growth, and institutional trust in the Ghanaian financial landscape. This is complemented by a **Warm Gold** (#FFC107) used sparingly for high-priority calls-to-action and motivational highlights, echoing the warmth of local commerce.

- **Primary**: Deep Forest Green for headers, primary buttons, and core branding.
- **Secondary/Accent**: Warm Gold for "Save Now" or "Pay Installment" actions.
- **Semantic**: Success Green (#027A48) for completed payments; MoMo Yellow (#FFCC00) specifically for Mobile Money integration touchpoints; Error Red (#D92D20) for failed transactions.
- **Neutrals**: A range of cool grays (Slate) for text and borders to maintain a crisp, professional fintech appearance.

## Typography

This design system uses **Hanken Grotesk** as the primary typeface for its clean, sharp, and contemporary aesthetic. It provides the "fintech-forward" look while remaining highly legible across varying mobile device qualities. 

For technical data, transaction IDs, and currency labels (GHS), **JetBrains Mono** is used to provide a sense of mathematical precision and system security.

- **Headlines**: Use heavy weights (600-700) with slight negative letter-spacing for a grounded, authoritative feel.
- **Body**: Standard weight (400) with generous line-height to ensure readability for users who may be skimming details on the go.
- **Currency**: Always display the GHS symbol with the same weight as the amount, ensuring clarity in financial totals.

## Layout & Spacing

The layout utilizes a **Fluid Grid** model with a focus on mobile-first constraints, as most users will interact via smartphones. 

- **Mobile (up to 599px)**: 4-column grid, 20px outside margins, 16px gutters.
- **Tablet (600px - 1023px)**: 8-column grid, 32px outside margins.
- **Desktop (1024px+)**: 12-column fixed grid (max-width 1200px), centered.

Spacing follows a 4px base unit. Use `md` (16px) for standard padding within cards and `lg` (24px) for vertical separation between distinct content sections.

## Elevation & Depth

To maintain a "trust-focused" environment, the design system avoids heavy shadows that can feel "muddy." Instead, it uses **Tonal Layers** and **Low-Contrast Outlines**.

- **Surface Levels**: The base background is slightly off-white (#F9FAFB). Cards sit on top of this in pure white (#FFFFFF).
- **Shadows**: Use a single, highly-diffused "Ambient Shadow" for primary cards: `0px 4px 20px rgba(16, 24, 40, 0.05)`. This creates a subtle lift without feeling artificial.
- **Interactivity**: On tap/hover, elements should not move "up" (no skeuomorphism); instead, they should use a subtle inner stroke or a slight color shift to indicate state change.

## Shapes

The design system adopts a **Rounded** (0.5rem / 8px) corner strategy. This provides a balance between the seriousness of a bank (sharp) and the friendliness of a social app (pill).

- **Standard Elements**: 8px (Buttons, Input Fields, Small Cards).
- **Large Containers**: 16px (Main dashboard sections, Product display cards).
- **Progress Bars**: 4px (Softly rounded to indicate a continuous flow of movement).

## Components

### Buttons & Calls-to-Action
- **Primary**: Deep Forest Green background, white text. Used for "Confirm Payment" or "Start Plan."
- **Secondary**: Clear border (1px) in Deep Forest Green, no fill. Used for "View Schedule."
- **MoMo Action**: A specialized button variant using MoMo Yellow with black text, specifically for the "Pay via Mobile Money" step, instantly recognizable to the local user.

### Progress & Status
- **Layaway Progress Bar**: A 2-tier bar. The track is a light tint of the primary color. The filled portion is Primary Green. If a payment is overdue, the filled portion turns to a subtle amber.
- **Payment Status Indicators**: Circular badges with icons.
    - *Pending*: Amber "clock" icon.
    - *Completed*: Green "check" icon.
    - *Processing*: Animated pulse on the primary green.

### Cards
- **Plan Card**: Displays the item image, total GHS price, and a prominent "XX% Completed" progress bar.
- **Transaction Item**: A list-based card with the MoMo or Bank icon on the left, amount in the middle (bold), and date/status on the right.

### Input Fields
- Floating labels are preferred to save vertical space on mobile.
- Currency inputs must always prefix with a static "GHS" label in the `label-font` (JetBrains Mono) to reinforce financial context.

### Mobile Money (MoMo) Integration
- Dedicated component for phone number entry that includes a provider selector (MTN, Vodafone/Telecel, AirtelTigo) to streamline the most common payment path.