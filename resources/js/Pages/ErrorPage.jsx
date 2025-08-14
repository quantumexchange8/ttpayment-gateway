export default function ErrorPage({ status }) {
    const title = {
      503: '503: Service Unavailable',
      500: '500: Server Error',
      404: '404: Page Not Found',
      403: '403: Forbidden',
    }[status]
  
    const description = {
      503: 'Sorry, we are doing some maintenance. Please check back soon.',
      500: 'Whoops, something went wrong on our servers.',
      404: 'Sorry, the page you are looking for could not be found.',
      403: 'Sorry, you are forbidden from accessing this page.',
    }[status]
  
    return (
      <div className="flex flex-col justify-center w-full min-h-screen items-center">
        <div className="max-w-80 md:max-w-xl">
            {
                status === 500 && <img src="/assets/error500.jpg" alt=""  />
            }
            {
                status === 503 && <img src="/assets/error503.jpg" alt=""  />
            }
            {
                status === 404 && <img src="/assets/error404.jpg" alt=""  />
            }
            
        </div>
        <div className="text-gray-950 font-bold text-lg">{description}</div>
      </div>
    )
  }