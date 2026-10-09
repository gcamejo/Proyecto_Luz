import { seoContent } from '../content/seoContent.js'

const normalizePath = pathname => (pathname.length > 1 ? pathname.replace(/\/+$/, '') : pathname) || '/'

export const resolveSeo = (pathname, origin = '') => {
  const path = normalizePath(pathname)
  const route = seoContent.routes[path]

  if (!route) {
    return { title: seoContent.privateTitle, description: seoContent.defaultDescription, robots: 'noindex, nofollow', canonical: null }
  }

  return {
    title: route.title,
    description: route.description,
    robots: 'index, follow',
    canonical: origin ? `${origin}${route.canonicalPath || path}` : null,
  }
}

const upsertHeadTag = (selector, tagName, attributes) => {
  let element = document.head.querySelector(selector)
  if (!element) {
    element = document.createElement(tagName)
    document.head.appendChild(element)
  }
  Object.entries(attributes).forEach(([name, value]) => element.setAttribute(name, value))
  return element
}

export const applySeo = seo => {
  document.title = seo.title
  upsertHeadTag('meta[name="description"]', 'meta', { name: 'description', content: seo.description })
  upsertHeadTag('meta[name="robots"]', 'meta', { name: 'robots', content: seo.robots })
  upsertHeadTag('meta[property="og:title"]', 'meta', { property: 'og:title', content: seo.title })
  upsertHeadTag('meta[property="og:description"]', 'meta', { property: 'og:description', content: seo.description })

  const canonical = document.head.querySelector('link[rel="canonical"]')
  if (seo.canonical) upsertHeadTag('link[rel="canonical"]', 'link', { rel: 'canonical', href: seo.canonical })
  else canonical?.remove()
}
