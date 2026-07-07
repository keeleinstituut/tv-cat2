import { each, isArrayLikeObject, isPlainObject, isEmpty } from 'lodash'
import { clsx, type ClassValue } from "clsx"
import { twMerge } from "tailwind-merge"

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs))
}

export const flattenJsonForRequest = (json: any) => {
  console.log(json)
  const inner = (result: any, value: any, prefix: any) => {
    console.log("prefix: " + prefix)
    console.log(value)
    if (isArrayLikeObject(value)) {
      each(value, element => {
        inner(result, element, `${prefix}[]`)
      })
    } else if (isPlainObject(value)) {
      each(value, (element, key) => {
        const prefixedKey = isEmpty(prefix) ? key : `${prefix}.${key}`
        inner(result, element, prefixedKey)
      })
    } else {
      result.push([prefix, value])
    }
  }

  const accumulator: any = []
  inner(accumulator, json, '')
  return accumulator
}

export const jsonToFormData = (json: any) => {
  const formData = new FormData()

  const flatData = flattenJsonForRequest(json)
  each(flatData, ([key, value]) => {
    formData.append(key, value)
  })

  return formData
}

export const triggerBrowserDownload = (options: { data: BlobPart, fileName: string }) => {
  const { data, fileName } = options
  const file = new File([data], fileName)
  const url = URL.createObjectURL(file)
  const a = document.createElement('a')
  a.href = url
  a.download = fileName
  a.click()
  a.remove()
}